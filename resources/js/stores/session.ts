import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { startAuthentication, startRegistration } from '@simplewebauthn/browser';
import { api, ApiError } from '@/api/client';
import { deriveAuthHash, deriveMasterKey, deriveWrappingKey, generateSalt, DEFAULT_ITERATIONS } from '@/crypto/kdf';
import { generateVaultKey, wrapVaultKey, unwrapVaultKey } from '@/crypto/vault-key';
import { base64ToBytes, bytesToBase64 } from '@/crypto/base64';
import type { Bytes } from '@/crypto/bytes';
import { setVaultKey, getVaultKey, clearVaultKey, hasVaultKey } from './vault-key-holder';

/**
 * - `anonymous`     no session on the server.
 * - `locked`        the server knows us, but the Vault Key is not in memory.
 * - `unlocked`      the Vault Key is loaded and items can be decrypted.
 */
export type SessionState = 'anonymous' | 'locked' | 'unlocked';

interface AccountInfo {
    email: string;
    salt: string;
    kdf_algo: 'pbkdf2-sha256';
    kdf_iterations: number;
    wrapped_vault_key: string;
    vault_key_iv: string;
    auto_lock_seconds: number;
}

interface PreloginInfo {
    salt: string;
    kdf_algo: 'pbkdf2-sha256';
    kdf_iterations: number;
}

export const MIN_MASTER_PASSWORD_LENGTH = 12;

export const useSessionStore = defineStore('session', () => {
    const state = ref<SessionState>('anonymous');
    const account = ref<AccountInfo | null>(null);
    const booted = ref(false);

    const email = computed(() => account.value?.email ?? '');
    const autoLockSeconds = computed(() => account.value?.auto_lock_seconds ?? 900);

    /** Asks the server whether the cookie we are carrying still means anything. */
    async function boot(): Promise<void> {
        try {
            account.value = await api.get<AccountInfo>('/api/account/me');
            state.value = hasVaultKey() ? 'unlocked' : 'locked';
        } catch (error) {
            if (error instanceof ApiError && error.isUnauthenticated) {
                reset();
            } else {
                throw error;
            }
        } finally {
            booted.value = true;
        }
    }

    /**
     * Full sign-in: master password, then passkey, then unwrap.
     *
     * The master key is zeroed before returning — only the Vault Key stays, and
     * it lives outside this store.
     */
    async function login(address: string, password: string): Promise<void> {
        const prelogin = await api.post<PreloginInfo>('/api/auth/prelogin', { email: address });

        const masterKey = await deriveMasterKey(password, {
            algo: prelogin.kdf_algo,
            iterations: prelogin.kdf_iterations,
            salt: base64ToBytes(prelogin.salt),
        });

        try {
            const authHash = await deriveAuthHash(masterKey);

            // A wrong password fails here, before the passkey is ever touched.
            await api.post('/api/auth/login', { email: address, auth_hash: bytesToBase64(authHash) });
            authHash.fill(0);

            const options = await api.post<Record<string, unknown>>('/api/auth/webauthn/challenge');
            const assertion = await startAuthentication({ optionsJSON: options as never });

            const verified = await api.post<{
                wrapped_vault_key: string;
                vault_key_iv: string;
                auto_lock_seconds: number;
            }>('/api/auth/webauthn/verify', { assertion });

            await unwrapInto(masterKey, verified.wrapped_vault_key, verified.vault_key_iv);

            // The account record comes from the server rather than being pieced
            // together here, so an unlock later works off exactly what the server
            // holds today.
            account.value = await api.get<AccountInfo>('/api/account/me');
            state.value = 'unlocked';
        } finally {
            masterKey.fill(0);
        }
    }

    /**
     * Re-derives the wrapping key from the master password alone. The passkey is
     * not asked for again: the server session is still open, so the second factor
     * has already been satisfied for this session.
     */
    async function unlock(password: string): Promise<void> {
        if (account.value === null) throw new Error('There is no session to unlock.');

        const masterKey = await deriveMasterKey(password, {
            algo: account.value.kdf_algo,
            iterations: account.value.kdf_iterations,
            salt: base64ToBytes(account.value.salt),
        });

        try {
            await unwrapInto(masterKey, account.value.wrapped_vault_key, account.value.vault_key_iv);
            state.value = 'unlocked';
        } finally {
            masterKey.fill(0);
        }
    }

    /** First-run setup: every piece of key material is generated here, in the browser. */
    async function completeSetup(token: string, password: string): Promise<void> {
        const salt = generateSalt();
        const masterKey = await deriveMasterKey(password, {
            algo: 'pbkdf2-sha256',
            iterations: DEFAULT_ITERATIONS,
            salt,
        });

        try {
            const authHash = await deriveAuthHash(masterKey);
            const wrappingKey = await deriveWrappingKey(masterKey);
            const vaultKey = generateVaultKey();
            const wrapped = await wrapVaultKey(wrappingKey, vaultKey);
            wrappingKey.fill(0);

            await api.post('/api/setup', {
                token,
                salt: bytesToBase64(salt),
                kdf_algo: 'pbkdf2-sha256',
                kdf_iterations: DEFAULT_ITERATIONS,
                auth_hash: bytesToBase64(authHash),
                wrapped_vault_key: wrapped.ciphertext,
                vault_key_iv: wrapped.iv,
            });
            authHash.fill(0);

            // The server left this session authenticated so the first passkey can
            // be enrolled straight away.
            setVaultKey(vaultKey);
            account.value = await api.get<AccountInfo>('/api/account/me');
            state.value = 'unlocked';
        } finally {
            masterKey.fill(0);
        }
    }

    /**
     * Changes the master password without touching a single item.
     *
     * The Vault Key does not change: it is simply re-wrapped with a wrapping key
     * derived from the new password. That is what makes this cheap and atomic
     * instead of a re-encryption of the whole vault.
     */
    async function rotateMasterPassword(currentPassword: string, newPassword: string): Promise<void> {
        if (account.value === null) throw new Error('There is no session.');

        const currentMasterKey = await deriveMasterKey(currentPassword, {
            algo: account.value.kdf_algo,
            iterations: account.value.kdf_iterations,
            salt: base64ToBytes(account.value.salt),
        });

        const salt = generateSalt();
        const newMasterKey = await deriveMasterKey(newPassword, {
            algo: 'pbkdf2-sha256',
            iterations: DEFAULT_ITERATIONS,
            salt,
        });

        try {
            const currentAuthHash = await deriveAuthHash(currentMasterKey);
            const newAuthHash = await deriveAuthHash(newMasterKey);
            const newWrappingKey = await deriveWrappingKey(newMasterKey);

            // The Vault Key is already in memory: there is nothing to unwrap.
            const wrapped = await wrapVaultKey(newWrappingKey, getVaultKey());
            newWrappingKey.fill(0);

            await api.post('/api/account/master-password', {
                current_auth_hash: bytesToBase64(currentAuthHash),
                salt: bytesToBase64(salt),
                kdf_algo: 'pbkdf2-sha256',
                kdf_iterations: DEFAULT_ITERATIONS,
                auth_hash: bytesToBase64(newAuthHash),
                wrapped_vault_key: wrapped.ciphertext,
                vault_key_iv: wrapped.iv,
            });

            currentAuthHash.fill(0);
            newAuthHash.fill(0);

            replaceKeyMaterial({
                salt: bytesToBase64(salt),
                kdf_iterations: DEFAULT_ITERATIONS,
                wrapped_vault_key: wrapped.ciphertext,
                vault_key_iv: wrapped.iv,
            });
        } finally {
            currentMasterKey.fill(0);
            newMasterKey.fill(0);
        }
    }

    /** Enrolls a passkey on the current account. */
    async function registerPasskey(name: string): Promise<void> {
        const options = await api.post<Record<string, unknown>>('/api/account/passkeys/challenge');
        const credential = await startRegistration({ optionsJSON: options as never });

        await api.post('/api/account/passkeys', { name, credential });
    }

    /** Drops the Vault Key but keeps the server session, so unlocking is cheap. */
    function lock(): void {
        clearVaultKey();
        if (state.value === 'unlocked') state.value = 'locked';
    }

    async function logout(): Promise<void> {
        try {
            await api.post('/api/auth/logout');
        } finally {
            reset();
        }
    }

    function reset(): void {
        clearVaultKey();
        account.value = null;
        state.value = 'anonymous';
    }

    function setAutoLockSeconds(seconds: number): void {
        if (account.value !== null) account.value.auto_lock_seconds = seconds;
    }

    /** Records the new key material after a master password rotation. */
    function replaceKeyMaterial(info: Pick<AccountInfo, 'salt' | 'kdf_iterations' | 'wrapped_vault_key' | 'vault_key_iv'>): void {
        if (account.value === null) return;

        account.value = { ...account.value, ...info };
    }

    async function unwrapInto(masterKey: Bytes, wrappedVaultKey: string, iv: string): Promise<void> {
        const wrappingKey = await deriveWrappingKey(masterKey);

        try {
            setVaultKey(await unwrapVaultKey(wrappingKey, { ciphertext: wrappedVaultKey, iv }));
        } finally {
            wrappingKey.fill(0);
        }
    }

    return {
        state,
        account,
        booted,
        email,
        autoLockSeconds,
        boot,
        login,
        unlock,
        completeSetup,
        rotateMasterPassword,
        registerPasskey,
        lock,
        logout,
        reset,
        setAutoLockSeconds,
        replaceKeyMaterial,
    };
});
