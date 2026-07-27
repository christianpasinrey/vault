import { describe, it, expect } from 'vitest';
import { encryptItem, decryptItem } from './item-crypto';
import { generateVaultKey } from './vault-key';
import type { ItemPlano } from '@/types/item';

const login: ItemPlano = {
    type: 'login',
    name: 'GitHub',
    folder: 'dev',
    favorite: true,
    fields: { url: 'https://github.com', username: 'chris', password: 'p4ss w0rd n', notes: '' },
};

describe('cifrado de items', () => {
    it('descifra exactamente el item que cifro', async () => {
        const vk = generateVaultKey();
        expect(await decryptItem(vk, await encryptItem(vk, login))).toEqual(login);
    });

    it('conserva texto largo multilinea sin alterarlo', async () => {
        const vk = generateVaultKey();
        const contenido = '-----BEGIN PRIVATE KEY-----\nlinea1\nlinea2\n\n-----END PRIVATE KEY-----\n';

        const item: ItemPlano = {
            type: 'secure_text',
            name: 'SSH prod',
            folder: 'infra',
            favorite: false,
            fields: { content: contenido, notes: '' },
        };

        expect((await decryptItem(vk, await encryptItem(vk, item))).fields.content).toBe(contenido);
    });

    it('no deja el nombre ni el tipo legibles en el ciphertext', async () => {
        const vk = generateVaultKey();
        const { ciphertext } = await encryptItem(vk, login);

        expect(ciphertext).not.toContain('GitHub');
        expect(ciphertext).not.toContain('login');
        expect(atob(ciphertext)).not.toContain('GitHub');
    });

    it('falla al descifrar con otra Vault Key', async () => {
        const cifrado = await encryptItem(generateVaultKey(), login);

        await expect(decryptItem(generateVaultKey(), cifrado)).rejects.toThrow();
    });

    it('falla si el ciphertext ha sido manipulado', async () => {
        const vk = generateVaultKey();
        const cifrado = await encryptItem(vk, login);
        const roto = cifrado.ciphertext.slice(0, -8) + 'AAAAAAAA';

        await expect(decryptItem(vk, { ...cifrado, ciphertext: roto })).rejects.toThrow();
    });
});
