import { describe, it, expect } from 'vitest';
import { generateVaultKey, wrapVaultKey, unwrapVaultKey } from './vault-key';
import { deriveMasterKey, deriveWrappingKey, generateSalt } from './kdf';

const hex = (b: Uint8Array) => Array.from(b).map((x) => x.toString(16).padStart(2, '0')).join('');

async function wrappingKeyDe(password: string, salt: Uint8Array) {
    return deriveWrappingKey(await deriveMasterKey(password, { algo: 'pbkdf2-sha256', iterations: 1000, salt }));
}

describe('Vault Key', () => {
    it('genera 32 bytes distintos en cada llamada', () => {
        const a = generateVaultKey();
        const b = generateVaultKey();

        expect(a.length).toBe(32);
        expect(hex(a)).not.toBe(hex(b));
    });

    it('desenvuelve exactamente la clave que envolvio', async () => {
        const wk = await wrappingKeyDe('master correcta', generateSalt());
        const vk = generateVaultKey();

        expect(hex(await unwrapVaultKey(wk, await wrapVaultKey(wk, vk)))).toBe(hex(vk));
    });

    it('falla al desenvolver con una master password incorrecta', async () => {
        const salt = generateSalt();
        const envuelta = await wrapVaultKey(await wrappingKeyDe('correcta', salt), generateVaultKey());

        await expect(unwrapVaultKey(await wrappingKeyDe('incorrecta', salt), envuelta)).rejects.toThrow();
    });

    it('permite rotar la master password sin cambiar la Vault Key', async () => {
        const saltViejo = generateSalt();
        const saltNuevo = generateSalt();
        const vk = generateVaultKey();

        const envueltaVieja = await wrapVaultKey(await wrappingKeyDe('vieja', saltViejo), vk);

        // Rotar = desenvolver con la vieja y volver a envolver con la nueva.
        // Los items no se tocan porque la Vault Key no cambia.
        const recuperada = await unwrapVaultKey(await wrappingKeyDe('vieja', saltViejo), envueltaVieja);
        const envueltaNueva = await wrapVaultKey(await wrappingKeyDe('nueva', saltNuevo), recuperada);

        expect(hex(await unwrapVaultKey(await wrappingKeyDe('nueva', saltNuevo), envueltaNueva))).toBe(hex(vk));
    });
});
