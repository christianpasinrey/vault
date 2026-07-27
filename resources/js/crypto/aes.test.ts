import { describe, it, expect } from 'vitest';
import { encryptBytes, decryptBytes } from './aes';
import { base64ToBytes, bytesToBase64 } from './base64';

const clave = () => crypto.getRandomValues(new Uint8Array(32));
const enc = new TextEncoder();
const dec = new TextDecoder();

describe('AES-256-GCM', () => {
    it('descifra lo que cifra', async () => {
        const k = clave();
        const original = enc.encode('contrasena muy secreta');
        const resultado = await decryptBytes(k, await encryptBytes(k, original));

        expect(dec.decode(resultado)).toBe('contrasena muy secreta');
    });

    it('usa un IV de 12 bytes', async () => {
        const { iv } = await encryptBytes(clave(), enc.encode('x'));
        expect(base64ToBytes(iv).length).toBe(12);
    });

    it('nunca repite el IV entre escrituras', async () => {
        const k = clave();
        const ivs = new Set<string>();
        for (let i = 0; i < 200; i++) ivs.add((await encryptBytes(k, enc.encode('x'))).iv);

        expect(ivs.size).toBe(200);
    });

    it('produce ciphertext distinto para el mismo plaintext', async () => {
        const k = clave();
        const a = await encryptBytes(k, enc.encode('igual'));
        const b = await encryptBytes(k, enc.encode('igual'));

        expect(a.ciphertext).not.toBe(b.ciphertext);
    });

    it('falla si se altera un byte del ciphertext', async () => {
        const k = clave();
        const cifrado = await encryptBytes(k, enc.encode('integro'));
        const bytes = base64ToBytes(cifrado.ciphertext);
        bytes[0] ^= 0xff;

        await expect(decryptBytes(k, { ciphertext: bytesToBase64(bytes), iv: cifrado.iv })).rejects.toThrow();
    });

    it('falla si se altera el IV', async () => {
        const k = clave();
        const cifrado = await encryptBytes(k, enc.encode('integro'));
        const iv = base64ToBytes(cifrado.iv);
        iv[0] ^= 0xff;

        await expect(decryptBytes(k, { ciphertext: cifrado.ciphertext, iv: bytesToBase64(iv) })).rejects.toThrow();
    });

    it('falla con una clave incorrecta', async () => {
        const cifrado = await encryptBytes(clave(), enc.encode('secreto'));

        await expect(decryptBytes(clave(), cifrado)).rejects.toThrow();
    });
});
