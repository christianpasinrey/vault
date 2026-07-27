import { bytesToBase64, base64ToBytes } from './base64';
import type { Bytes } from './bytes';

const IV_BYTES = 12;

export interface Cifrado {
    ciphertext: string;
    iv: string;
}

async function importar(key: Bytes, uso: KeyUsage[]): Promise<CryptoKey> {
    return crypto.subtle.importKey('raw', key, { name: 'AES-GCM' }, false, uso);
}

export async function encryptBytes(key: Bytes, datos: Bytes): Promise<Cifrado> {
    // IV nuevo en cada escritura. Reutilizarlo con la misma clave rompe GCM por completo.
    const iv = crypto.getRandomValues(new Uint8Array(IV_BYTES));

    const cifrado = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await importar(key, ['encrypt']), datos);

    return { ciphertext: bytesToBase64(new Uint8Array(cifrado)), iv: bytesToBase64(iv) };
}

export async function decryptBytes(key: Bytes, cifrado: Cifrado): Promise<Bytes> {
    // GCM autentica: si el ciphertext o el IV han sido manipulados, esto lanza.
    const plano = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: base64ToBytes(cifrado.iv) },
        await importar(key, ['decrypt']),
        base64ToBytes(cifrado.ciphertext),
    );

    return new Uint8Array(plano);
}
