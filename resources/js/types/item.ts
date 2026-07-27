export type ItemType = 'login' | 'api_key' | 'secure_text';

/** Plaintext contents of an item. Never leaves the browser. */
export interface PlainItem {
    type: ItemType;
    name: string;
    folder: string;
    favorite: boolean;
    fields: Record<string, string>;
}

/** What the server returns and accepts: completely opaque. */
export interface EncryptedItem {
    id: string;
    ciphertext: string;
    iv: string;
    version: number;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
}

/** An item decrypted in memory, along with its server-side metadata. */
export interface DecryptedItem extends PlainItem {
    id: string;
    version: number;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
}

export const FIELDS_BY_TYPE: Record<ItemType, string[]> = {
    login: ['url', 'username', 'password', 'notes'],
    api_key: ['service', 'key', 'secret', 'environment', 'expires_at', 'notes'],
    secure_text: ['content', 'notes'],
};

/** Fields kept hidden until the user asks to reveal them. */
export const SECRET_FIELDS: Record<ItemType, string[]> = {
    login: ['password'],
    api_key: ['key', 'secret'],
    secure_text: ['content'],
};

export const TYPE_LABEL: Record<ItemType, string> = {
    login: 'Website login',
    api_key: 'API key',
    secure_text: 'Secure note',
};
