import { encryptBytes, decryptBytes, type Encrypted } from './aes';
import type { Bytes } from './bytes';
import type { PlainItem } from '@/types/item';

const enc = new TextEncoder();
const dec = new TextDecoder();

/**
 * The whole item travels inside the ciphertext: name, folder, type and fields.
 * The server never receives a single plaintext metadata field.
 */
export function encryptItem(vaultKey: Bytes, item: PlainItem): Promise<Encrypted> {
    return encryptBytes(vaultKey, enc.encode(JSON.stringify(item)));
}

export async function decryptItem(vaultKey: Bytes, encrypted: Encrypted): Promise<PlainItem> {
    return JSON.parse(dec.decode(await decryptBytes(vaultKey, encrypted))) as PlainItem;
}
