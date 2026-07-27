import { encryptBytes, decryptBytes, type Cifrado } from './aes';
import type { Bytes } from './bytes';
import type { ItemPlano } from '@/types/item';

const enc = new TextEncoder();
const dec = new TextDecoder();

/**
 * El item entero viaja dentro del ciphertext: nombre, carpeta, tipo y campos.
 * El servidor no recibe ni un metadato de contenido en claro.
 */
export function encryptItem(vaultKey: Bytes, item: ItemPlano): Promise<Cifrado> {
    return encryptBytes(vaultKey, enc.encode(JSON.stringify(item)));
}

export async function decryptItem(vaultKey: Bytes, cifrado: Cifrado): Promise<ItemPlano> {
    return JSON.parse(dec.decode(await decryptBytes(vaultKey, cifrado))) as ItemPlano;
}
