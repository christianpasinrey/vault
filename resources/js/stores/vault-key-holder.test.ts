import { describe, it, expect, beforeEach } from 'vitest';
import { setVaultKey, getVaultKey, hasVaultKey, clearVaultKey } from './vault-key-holder';
import { generateVaultKey } from '@/crypto/vault-key';

describe('Vault Key custody', () => {
    beforeEach(() => clearVaultKey());

    it('returns the key it was handed', () => {
        const key = generateVaultKey();
        setVaultKey(key);

        expect(getVaultKey()).toBe(key);
    });

    it('throws when the key is requested while locked', () => {
        expect(() => getVaultKey()).toThrow(/locked/i);
    });

    it('reports whether it is holding a key', () => {
        expect(hasVaultKey()).toBe(false);
        setVaultKey(generateVaultKey());
        expect(hasVaultKey()).toBe(true);
    });

    it('forgets the key on lock', () => {
        setVaultKey(generateVaultKey());
        clearVaultKey();

        expect(() => getVaultKey()).toThrow();
    });

    it('overwrites the bytes of the previous key on lock', () => {
        const key = generateVaultKey();
        const copy = new Uint8Array(key);
        setVaultKey(key);
        clearVaultKey();

        expect(Array.from(key)).not.toEqual(Array.from(copy));
        expect(key.every((b) => b === 0)).toBe(true);
    });

    it('overwrites the previous key when a new one replaces it', () => {
        const first = generateVaultKey();
        setVaultKey(first);
        setVaultKey(generateVaultKey());

        expect(first.every((b) => b === 0)).toBe(true);
    });
});
