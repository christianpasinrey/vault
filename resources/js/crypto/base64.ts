import type { Bytes } from './bytes';

/** Conversion between Uint8Array and base64, with no external dependencies. */

export function bytesToBase64(bytes: Uint8Array): string {
    let binary = '';
    for (const byte of bytes) binary += String.fromCharCode(byte);

    return btoa(binary);
}

export function base64ToBytes(b64: string): Bytes {
    const binary = atob(b64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

    return bytes;
}
