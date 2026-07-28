import type { Bytes } from '@/crypto/bytes';

/**
 * Custody of the Vault Key.
 *
 * It lives in a module variable, deliberately outside Pinia: that way it never
 * shows up in the Vue devtools, is never serialized, and does not survive a page
 * reload. It must never reach localStorage, sessionStorage or IndexedDB.
 */

let vaultKey: Bytes | null = null;

export function setVaultKey(key: Bytes): void {
    // Replacing a key still means the old one has to go.
    clearVaultKey();
    vaultKey = key;
}

export function getVaultKey(): Bytes {
    if (vaultKey === null) throw new Error('The vault is locked.');

    return vaultKey;
}

export function hasVaultKey(): boolean {
    return vaultKey !== null;
}

/** Overwrites the bytes before dropping the reference. */
export function clearVaultKey(): void {
    if (vaultKey !== null) vaultKey.fill(0);
    vaultKey = null;
}
