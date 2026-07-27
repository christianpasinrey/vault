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
 * Segundo factor obligatorio.
 *
 * La passkey no cifra nada: solo decide si el servidor entrega la Vault Key
 * envuelta. Sin la master password esa clave sigue siendo un blob inútil, así
 * que un fallo aquí no compromete el contenido de la bóveda, solo su acceso.
 */
class WebauthnService
{
    /** El reto vive en sesión y se consume en el primer intento, válido o no. */
    public const CLAVE_RETO = 'webauthn_challenge';

    private const RETO_BYTES = 32;

    private SerializerInterface $serializador;

    public function __construct()
    {
        $this->serializador = (new WebauthnSerializerFactory(
            AttestationStatementSupportManager::create([new NoneAttestationStatementSupport()])
        ))->create();
    }

    /** Opciones para registrar una passkey nueva. Guarda el reto en sesión. */
    public function opcionesDeRegistro(User $usuario): array
    {
        $opciones = PublicKeyCredentialCreationOptions::create(
            rp: $this->entidadDelServidor(),
            user: $this->entidadDelUsuario($usuario),
            challenge: random_bytes(self::RETO_BYTES),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            // Impide registrar dos veces el mismo autenticador.
            excludeCredentials: $this->descriptores($usuario),
        );

        return $this->guardarReto($opciones);
    }

    /** Valida la respuesta del navegador y persiste la credencial. */
    public function registrar(User $usuario, array $credencial, string $nombre): void
    {
        $opciones = $this->recuperarReto(PublicKeyCredentialCreationOptions::class);

        $respuesta = $this->respuestaDelNavegador($credencial);
        abort_unless($respuesta instanceof AuthenticatorAttestationResponse, 422, 'La respuesta no es un registro.');

        $registro = AuthenticatorAttestationResponseValidator::create($this->ceremonias()->creationCeremony())
            ->check($respuesta, $opciones, $this->idDelServidor());

        WebauthnCredential::create([
            'user_id' => $usuario->id,
            'credential_id' => Base64UrlSafe::encodeUnpadded($registro->publicKeyCredentialId),
            'public_key' => $this->serializador->serialize(
                PublicKeyCredentialSource::fromCredentialRecord($registro), 'json'
            ),
            'sign_count' => $registro->counter,
            'name' => $nombre,
        ]);
    }

    /** Opciones de aserción para iniciar sesión. Guarda el reto en sesión. */
    public function opcionesDeAsercion(User $usuario): array
    {
        return $this->guardarReto(PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(self::RETO_BYTES),
            rpId: $this->idDelServidor(),
            allowCredentials: $this->descriptores($usuario),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        ));
    }

    /** Valida la aserción; devuelve true y actualiza sign_count si es correcta. */
    public function verificar(User $usuario, array $asercion): bool
    {
        $opciones = $this->recuperarReto(PublicKeyCredentialRequestOptions::class);

        try {
            $credencial = $this->serializador->deserialize(
                json_encode($asercion, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json'
            );

            $respuesta = $credencial->response;

            if (! $respuesta instanceof AuthenticatorAssertionResponse) {
                return false;
            }

            $fila = $usuario->webauthnCredentials()
                ->where('credential_id', Base64UrlSafe::encodeUnpadded($credencial->rawId))
                ->first();

            if ($fila === null) {
                return false;
            }

            $registro = $this->serializador->deserialize(
                $fila->public_key, PublicKeyCredentialSource::class, 'json'
            );

            $actualizado = AuthenticatorAssertionResponseValidator::create($this->ceremonias()->requestCeremony())
                ->check($registro, $respuesta, $opciones, $this->idDelServidor(), (string) $usuario->id);

            $fila->update([
                'public_key' => $this->serializador->serialize(
                    PublicKeyCredentialSource::fromCredentialRecord($actualizado), 'json'
                ),
                'sign_count' => $actualizado->counter,
                'last_used_at' => now(),
            ]);

            return true;
        } catch (Throwable) {
            // Cualquier fallo de la ceremonia es indistinguible de una firma
            // inválida: no damos pistas sobre en qué paso concreto falló.
            return false;
        }
    }

    private function ceremonias(): CeremonyStepManagerFactory
    {
        $fabrica = new CeremonyStepManagerFactory();
        $fabrica->setAllowedOrigins([rtrim((string) config('app.url'), '/')]);

        return $fabrica;
    }

    private function idDelServidor(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    private function entidadDelServidor(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create((string) config('app.name'), $this->idDelServidor());
    }

    private function entidadDelUsuario(User $usuario): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create($usuario->email, (string) $usuario->id, $usuario->email);
    }

    /** @return PublicKeyCredentialDescriptor[] */
    private function descriptores(User $usuario): array
    {
        return $usuario->webauthnCredentials
            ->map(fn (WebauthnCredential $c) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64UrlSafe::decodeNoPadding($c->credential_id),
            ))
            ->all();
    }

    /**
     * Guarda las opciones serializadas en sesión y devuelve su forma JSON, para
     * que la verificación posterior use exactamente el mismo objeto que se envió.
     */
    private function guardarReto(object $opciones): array
    {
        $json = $this->serializador->serialize($opciones, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);

        Session::put(self::CLAVE_RETO, $json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @template T of object
     *  @param  class-string<T>  $tipo
     *  @return T */
    private function recuperarReto(string $tipo): object
    {
        $json = Session::pull(self::CLAVE_RETO);

        abort_if($json === null, 403, 'No hay ningún reto pendiente.');

        return $this->serializador->deserialize($json, $tipo, 'json');
    }
}
