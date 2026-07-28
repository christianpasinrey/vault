import type { Bytes } from './bytes';

/**
 * Key derivation from the master password.
 *
 * The master key never leaves this module without going through HKDF: the auth
 * hash that travels to the server and the wrapping key that decrypts the Vault
 * Key are computationally independent subkeys. Knowing one does not help you
 * obtain the other.
 */

export const DEFAULT_ITERATIONS = 600000;

const SALT_BYTES = 16;
const INFO_AUTH = 'vault:auth:v1';
const INFO_WRAP = 'vault:wrap:v1';

const enc = new TextEncoder();

export interface KdfParams {
    algo: 'pbkdf2-sha256';
    iterations: number;
    salt: Bytes;
}

export function generateSalt(): Bytes {
    return crypto.getRandomValues(new Uint8Array(SALT_BYTES));
}

export async function deriveMasterKey(password: string, params: KdfParams): Promise<Bytes> {
    const base = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveBits']);

    const bits = await crypto.subtle.deriveBits(
        { name: 'PBKDF2', salt: params.salt, iterations: params.iterations, hash: 'SHA-256' },
        base,
        256,
    );

    return new Uint8Array(bits);
}

async function deriveSubkey(masterKey: Bytes, info: string): Promise<Bytes> {
    const base = await crypto.subtle.importKey('raw', masterKey, 'HKDF', false, ['deriveBits']);

    const bits = await crypto.subtle.deriveBits(
        { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: enc.encode(info) },
        base,
        256,
    );

    return new Uint8Array(bits);
}

/** Credential sent to the server. Useless for decryption. */
export function deriveAuthHash(masterKey: Bytes): Promise<Bytes> {
    return deriveSubkey(masterKey, INFO_AUTH);
}

/** Key that wraps and unwraps the Vault Key. Never leaves the browser. */
export function deriveWrappingKey(masterKey: Bytes): Promise<Bytes> {
    return deriveSubkey(masterKey, INFO_WRAP);
}
