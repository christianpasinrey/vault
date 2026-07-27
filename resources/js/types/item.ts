export type ItemType = 'login' | 'api_key' | 'secure_text';

/** Contenido en claro de un item. Nunca abandona el navegador. */
export interface ItemPlano {
    type: ItemType;
    name: string;
    folder: string;
    favorite: boolean;
    fields: Record<string, string>;
}

/** Lo que devuelve y acepta el servidor: opaco por completo. */
export interface ItemCifrado {
    id: string;
    ciphertext: string;
    iv: string;
    version: number;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
}

/** Un item descifrado en memoria, con sus metadatos de servidor. */
export interface ItemDescifrado extends ItemPlano {
    id: string;
    version: number;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
}

export const CAMPOS_POR_TIPO: Record<ItemType, string[]> = {
    login: ['url', 'username', 'password', 'notes'],
    api_key: ['service', 'key', 'secret', 'environment', 'expires_at', 'notes'],
    secure_text: ['content', 'notes'],
};

/** Campos que se muestran ocultos hasta que el usuario pulsa revelar. */
export const CAMPOS_SECRETOS: Record<ItemType, string[]> = {
    login: ['password'],
    api_key: ['key', 'secret'],
    secure_text: ['content'],
};

export const ETIQUETA_TIPO: Record<ItemType, string> = {
    login: 'Acceso web',
    api_key: 'Clave de API',
    secure_text: 'Texto seguro',
};
