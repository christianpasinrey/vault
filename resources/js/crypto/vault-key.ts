import { encryptBytes, decryptBytes, type Encrypted } from './aes';
import type { Bytes } from './bytes';

const VAULT_KEY_BYTES = 32;

/**
 * Random key that encrypts every item. Generated once, when the account is
 * created, and never changed: that is why rotating the master password only
 * re-encrypts these 32 bytes instead of the whole vault.
 */
export function generateVaultKey(): Bytes {
    return crypto.getRandomValues(new Uint8Array(VAULT_KEY_BYTES));
}

export function wrapVaultKey(wrappingKey: Bytes, vaultKey: Bytes): Promise<Encrypted> {
    return encryptBytes(wrappingKey, vaultKey);
}

export function unwrapVaultKey(wrappingKey: Bytes, wrapped: Encrypted): Promise<Bytes> {
    return decryptBytes(wrappingKey, wrapped);
}
