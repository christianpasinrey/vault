import { bytesToBase64, base64ToBytes } from './base64';
import type { Bytes } from './bytes';

const IV_BYTES = 12;

export interface Encrypted {
    ciphertext: string;
    iv: string;
}

async function importKey(key: Bytes, usages: KeyUsage[]): Promise<CryptoKey> {
    return crypto.subtle.importKey('raw', key, { name: 'AES-GCM' }, false, usages);
}

export async function encryptBytes(key: Bytes, data: Bytes): Promise<Encrypted> {
    // A fresh IV on every write. Reusing one under the same key breaks GCM entirely.
    const iv = crypto.getRandomValues(new Uint8Array(IV_BYTES));

    const encrypted = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await importKey(key, ['encrypt']), data);

    return { ciphertext: bytesToBase64(new Uint8Array(encrypted)), iv: bytesToBase64(iv) };
}

export async function decryptBytes(key: Bytes, encrypted: Encrypted): Promise<Bytes> {
    // GCM authenticates: if the ciphertext or the IV were tampered with, this throws.
    const plaintext = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: base64ToBytes(encrypted.iv) },
        await importKey(key, ['decrypt']),
        base64ToBytes(encrypted.ciphertext),
    );

    return new Uint8Array(plaintext);
}
