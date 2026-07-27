import { describe, it, expect } from 'vitest';
import { generateVaultKey, wrapVaultKey, unwrapVaultKey } from './vault-key';
import { deriveMasterKey, deriveWrappingKey, generateSalt } from './kdf';
import type { Bytes } from './bytes';

const hex = (b: Uint8Array) => Array.from(b).map((x) => x.toString(16).padStart(2, '0')).join('');

async function wrappingKeyFrom(password: string, salt: Bytes) {
    return deriveWrappingKey(await deriveMasterKey(password, { algo: 'pbkdf2-sha256', iterations: 1000, salt }));
}

describe('Vault Key', () => {
    it('generates 32 distinct bytes on every call', () => {
        const a = generateVaultKey();
        const b = generateVaultKey();

        expect(a.length).toBe(32);
        expect(hex(a)).not.toBe(hex(b));
    });

    it('unwraps exactly the key it wrapped', async () => {
        const wk = await wrappingKeyFrom('correct master', generateSalt());
        const vk = generateVaultKey();

        expect(hex(await unwrapVaultKey(wk, await wrapVaultKey(wk, vk)))).toBe(hex(vk));
    });

    it('fails to unwrap with the wrong master password', async () => {
        const salt = generateSalt();
        const wrapped = await wrapVaultKey(await wrappingKeyFrom('correct', salt), generateVaultKey());

        await expect(unwrapVaultKey(await wrappingKeyFrom('wrong', salt), wrapped)).rejects.toThrow();
    });

    it('allows rotating the master password without changing the Vault Key', async () => {
        const oldSalt = generateSalt();
        const newSalt = generateSalt();
        const vk = generateVaultKey();

        const oldWrapped = await wrapVaultKey(await wrappingKeyFrom('old', oldSalt), vk);

        // Rotating = unwrap with the old key, wrap again with the new one.
        // Items are untouched because the Vault Key itself does not change.
        const recovered = await unwrapVaultKey(await wrappingKeyFrom('old', oldSalt), oldWrapped);
        const newWrapped = await wrapVaultKey(await wrappingKeyFrom('new', newSalt), recovered);

        expect(hex(await unwrapVaultKey(await wrappingKeyFrom('new', newSalt), newWrapped))).toBe(hex(vk));
    });
});
