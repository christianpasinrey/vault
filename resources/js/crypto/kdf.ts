/**
 * Derivacion de claves a partir de la master password.
 *
 * La master key nunca sale de aqui sin pasar por HKDF: el auth hash que viaja
 * al servidor y la wrapping key que descifra la Vault Key son subclaves
 * computacionalmente independientes. Conocer una no ayuda a obtener la otra.
 */

export const DEFAULT_ITERATIONS = 600000;

const SALT_BYTES = 16;
const INFO_AUTH = 'vault:auth:v1';
const INFO_WRAP = 'vault:wrap:v1';

const enc = new TextEncoder();

export interface KdfParams {
    algo: 'pbkdf2-sha256';
    iterations: number;
    salt: Uint8Array;
}

export function generateSalt(): Uint8Array {
    return crypto.getRandomValues(new Uint8Array(SALT_BYTES));
}

export async function deriveMasterKey(password: string, params: KdfParams): Promise<Uint8Array> {
    const base = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveBits']);

    const bits = await crypto.subtle.deriveBits(
        { name: 'PBKDF2', salt: params.salt, iterations: params.iterations, hash: 'SHA-256' },
        base,
        256,
    );

    return new Uint8Array(bits);
}

async function derivarSubclave(masterKey: Uint8Array, info: string): Promise<Uint8Array> {
    const base = await crypto.subtle.importKey('raw', masterKey, 'HKDF', false, ['deriveBits']);

    const bits = await crypto.subtle.deriveBits(
        { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: enc.encode(info) },
        base,
        256,
    );

    return new Uint8Array(bits);
}

/** Credencial que se envia al servidor. Inutil para descifrar. */
export function deriveAuthHash(masterKey: Uint8Array): Promise<Uint8Array> {
    return derivarSubclave(masterKey, INFO_AUTH);
}

/** Clave que envuelve y desenvuelve la Vault Key. Jamas abandona el navegador. */
export function deriveWrappingKey(masterKey: Uint8Array): Promise<Uint8Array> {
    return derivarSubclave(masterKey, INFO_WRAP);
}
