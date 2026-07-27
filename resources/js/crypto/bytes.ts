/**
 * Uint8Array respaldado por ArrayBuffer.
 *
 * Desde TypeScript 5.7 Uint8Array es generico sobre ArrayBufferLike, que
 * incluye SharedArrayBuffer. WebCrypto no acepta memoria compartida, asi que
 * todas las firmas criptograficas usan este alias en lugar del tipo suelto.
 */
export type Bytes = Uint8Array<ArrayBuffer>;
