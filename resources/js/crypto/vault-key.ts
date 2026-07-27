import { encryptBytes, decryptBytes, type Cifrado } from './aes';

const VAULT_KEY_BYTES = 32;

/**
 * Clave aleatoria que cifra todos los items. Se genera una sola vez, al crear
 * la cuenta, y no cambia nunca: por eso rotar la master password solo re-cifra
 * estos 32 bytes en lugar de la boveda entera.
 */
export function generateVaultKey(): Uint8Array {
    return crypto.getRandomValues(new Uint8Array(VAULT_KEY_BYTES));
}

export function wrapVaultKey(wrappingKey: Uint8Array, vaultKey: Uint8Array): Promise<Cifrado> {
    return encryptBytes(wrappingKey, vaultKey);
}

export function unwrapVaultKey(wrappingKey: Uint8Array, envuelta: Cifrado): Promise<Uint8Array> {
    return decryptBytes(wrappingKey, envuelta);
}
