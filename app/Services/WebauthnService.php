<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebauthnCredential;
use Cose\Algorithms;
use Illuminate\Support\Facades\Session;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Mandatory second factor.
 *
 * The passkey encrypts nothing: it only decides whether the server hands over
 * the wrapped Vault Key. Without the master password that key is still an
 * unusable blob, so a failure here compromises access to the vault, never its
 * contents.
 */
class WebauthnService
{
    /** The challenge lives in the session and is consumed on the first attempt, valid or not. */
    public const CHALLENGE_KEY = 'webauthn_challenge';

    private const CHALLENGE_BYTES = 32;

    private SerializerInterface $serializer;

    public function __construct()
    {
        $this->serializer = (new WebauthnSerializerFactory(
            AttestationStatementSupportManager::create([new NoneAttestationStatementSupport])
        ))->create();
    }

    /** Options for registering a new passkey. Stores the challenge in the session. */
    public function registrationOptions(User $user): array
    {
        $options = PublicKeyCredentialCreationOptions::create(
            rp: $this->relyingParty(),
            user: $this->userEntity($user),
            challenge: random_bytes(self::CHALLENGE_BYTES),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            // Stops the same authenticator being registered twice.
            excludeCredentials: $this->descriptors($user),
        );

        return $this->storeChallenge($options);
    }

    /** Validates the browser response and persists the credential. */
    public function register(User $user, array $credential, string $name): void
    {
        $options = $this->pullChallenge(PublicKeyCredentialCreationOptions::class);

        $response = $this->browserResponse($credential);
        abort_unless($response instanceof AuthenticatorAttestationResponse, 422, 'That is not a registration response.');

        $record = AuthenticatorAttestationResponseValidator::create($this->ceremonies()->creationCeremony())
            ->check($response, $options, $this->relyingPartyId());

        WebauthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
            'public_key' => $this->serializer->serialize(
                PublicKeyCredentialSource::fromCredentialRecord($record), 'json'
            ),
            'sign_count' => $record->counter,
            'name' => $name,
        ]);
    }

    /** Assertion options for signing in. Stores the challenge in the session. */
    public function assertionOptions(User $user): array
    {
        return $this->storeChallenge(PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(self::CHALLENGE_BYTES),
            rpId: $this->relyingPartyId(),
            allowCredentials: $this->descriptors($user),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        ));
    }

    /** Validates the assertion; returns true and updates sign_count when it checks out. */
    public function verify(User $user, array $assertion): bool
    {
        $options = $this->pullChallenge(PublicKeyCredentialRequestOptions::class);

        try {
            $credential = $this->serializer->deserialize(
                json_encode($assertion, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json'
            );

            $response = $credential->response;

            if (! $response instanceof AuthenticatorAssertionResponse) {
                return false;
            }

            $row = $user->webauthnCredentials()
                ->where('credential_id', Base64UrlSafe::encodeUnpadded($credential->rawId))
                ->first();

            if ($row === null) {
                return false;
            }

            $record = $this->serializer->deserialize(
                $row->public_key, PublicKeyCredentialSource::class, 'json'
            );

            $updated = AuthenticatorAssertionResponseValidator::create($this->ceremonies()->requestCeremony())
                ->check($record, $response, $options, $this->relyingPartyId(), (string) $user->id);

            $row->update([
                'public_key' => $this->serializer->serialize(
                    PublicKeyCredentialSource::fromCredentialRecord($updated), 'json'
                ),
                'sign_count' => $updated->counter,
                'last_used_at' => now(),
            ]);

            return true;
        } catch (Throwable) {
            // Any ceremony failure is indistinguishable from an invalid
            // signature: we give no hint about which step actually failed.
            return false;
        }
    }

    private function browserResponse(array $credential): mixed
    {
        return $this->serializer->deserialize(
            json_encode($credential, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json'
        )->response;
    }

    private function ceremonies(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins([rtrim((string) config('app.url'), '/')]);

        return $factory;
    }

    private function relyingPartyId(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    private function relyingParty(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create((string) config('app.name'), $this->relyingPartyId());
    }

    private function userEntity(User $user): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create($user->email, (string) $user->id, $user->email);
    }

    /** @return PublicKeyCredentialDescriptor[] */
    private function descriptors(User $user): array
    {
        return $user->webauthnCredentials
            ->map(fn (WebauthnCredential $c) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64UrlSafe::decodeNoPadding($c->credential_id),
            ))
            ->all();
    }

    /**
     * Stores the serialized options in the session and returns their JSON form,
     * so that verification later works against exactly the object that was sent.
     */
    private function storeChallenge(object $options): array
    {
        $json = $this->serializer->serialize($options, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);

        Session::put(self::CHALLENGE_KEY, $json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @template T of object
     *  @param  class-string<T>  $type
     *  @return T */
    private function pullChallenge(string $type): object
    {
        $json = Session::pull(self::CHALLENGE_KEY);

        abort_if($json === null, 403, 'There is no pending challenge.');

        return $this->serializer->deserialize($json, $type, 'json');
    }
}
