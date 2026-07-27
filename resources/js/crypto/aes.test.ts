import { describe, it, expect } from 'vitest';
import { encryptBytes, decryptBytes } from './aes';
import { base64ToBytes, bytesToBase64 } from './base64';

const key = () => crypto.getRandomValues(new Uint8Array(32));
const enc = new TextEncoder();
const dec = new TextDecoder();

describe('AES-256-GCM', () => {
    it('decrypts what it encrypts', async () => {
        const k = key();
        const original = enc.encode('a very secret password');
        const result = await decryptBytes(k, await encryptBytes(k, original));

        expect(dec.decode(result)).toBe('a very secret password');
    });

    it('uses a 12-byte IV', async () => {
        const { iv } = await encryptBytes(key(), enc.encode('x'));
        expect(base64ToBytes(iv).length).toBe(12);
    });

    it('never repeats the IV across writes', async () => {
        const k = key();
        const ivs = new Set<string>();
        for (let i = 0; i < 200; i++) ivs.add((await encryptBytes(k, enc.encode('x'))).iv);

        expect(ivs.size).toBe(200);
    });

    it('produces different ciphertext for the same plaintext', async () => {
        const k = key();
        const a = await encryptBytes(k, enc.encode('same'));
        const b = await encryptBytes(k, enc.encode('same'));

        expect(a.ciphertext).not.toBe(b.ciphertext);
    });

    it('fails when a ciphertext byte is altered', async () => {
        const k = key();
        const encrypted = await encryptBytes(k, enc.encode('intact'));
        const bytes = base64ToBytes(encrypted.ciphertext);
        bytes[0] ^= 0xff;

        await expect(decryptBytes(k, { ciphertext: bytesToBase64(bytes), iv: encrypted.iv })).rejects.toThrow();
    });

    it('fails when the IV is altered', async () => {
        const k = key();
        const encrypted = await encryptBytes(k, enc.encode('intact'));
        const iv = base64ToBytes(encrypted.iv);
        iv[0] ^= 0xff;

        await expect(decryptBytes(k, { ciphertext: encrypted.ciphertext, iv: bytesToBase64(iv) })).rejects.toThrow();
    });

    it('fails with the wrong key', async () => {
        const encrypted = await encryptBytes(key(), enc.encode('secret'));

        await expect(decryptBytes(key(), encrypted)).rejects.toThrow();
    });
});
