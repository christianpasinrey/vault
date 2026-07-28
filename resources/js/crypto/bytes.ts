/**
 * Uint8Array backed by an ArrayBuffer.
 *
 * Since TypeScript 5.7 Uint8Array is generic over ArrayBufferLike, which
 * includes SharedArrayBuffer. WebCrypto rejects shared memory, so every
 * cryptographic signature uses this alias instead of the bare type.
 */
export type Bytes = Uint8Array<ArrayBuffer>;
