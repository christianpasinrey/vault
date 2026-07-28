import { describe, it, expect } from 'vitest';
import { encryptItem, decryptItem } from './item-crypto';
import { generateVaultKey } from './vault-key';
import type { PlainItem } from '@/types/item';

const login: PlainItem = {
    type: 'login',
    name: 'GitHub',
    folder: 'dev',
    favorite: true,
    fields: { url: 'https://github.com', username: 'chris', password: 'p4ss w0rd n', notes: '' },
};

describe('item encryption', () => {
    it('decrypts exactly the item it encrypted', async () => {
        const vk = generateVaultKey();
        expect(await decryptItem(vk, await encryptItem(vk, login))).toEqual(login);
    });

    it('preserves long multiline text unaltered', async () => {
        const vk = generateVaultKey();
        const content = '-----BEGIN PRIVATE KEY-----\nline1\nline2\n\n-----END PRIVATE KEY-----\n';

        const item: PlainItem = {
            type: 'secure_text',
            name: 'SSH prod',
            folder: 'infra',
            favorite: false,
            fields: { content, notes: '' },
        };

        expect((await decryptItem(vk, await encryptItem(vk, item))).fields.content).toBe(content);
    });

    it('leaves neither the name nor the type readable in the ciphertext', async () => {
        const vk = generateVaultKey();
        const { ciphertext } = await encryptItem(vk, login);

        expect(ciphertext).not.toContain('GitHub');
        expect(ciphertext).not.toContain('login');
        expect(atob(ciphertext)).not.toContain('GitHub');
    });

    it('fails to decrypt with a different Vault Key', async () => {
        const encrypted = await encryptItem(generateVaultKey(), login);

        await expect(decryptItem(generateVaultKey(), encrypted)).rejects.toThrow();
    });

    it('fails when the ciphertext has been tampered with', async () => {
        const vk = generateVaultKey();
        const encrypted = await encryptItem(vk, login);
        const broken = encrypted.ciphertext.slice(0, -8) + 'AAAAAAAA';

        await expect(decryptItem(vk, { ...encrypted, ciphertext: broken })).rejects.toThrow();
    });
});
