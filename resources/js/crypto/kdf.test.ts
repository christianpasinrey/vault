import { describe, it, expect } from 'vitest';
import {
    deriveMasterKey,
    deriveAuthHash,
    deriveWrappingKey,
    generateSalt,
    DEFAULT_ITERATIONS,
    type KdfParams,
} from './kdf';

const hex = (b: Uint8Array) => Array.from(b).map((x) => x.toString(16).padStart(2, '0')).join('');
const enc = new TextEncoder();

const params = (sobrescribir: Partial<KdfParams> = {}): KdfParams => ({
    algo: 'pbkdf2-sha256',
    iterations: 1000,
    salt: generateSalt(),
    ...sobrescribir,
});

describe('deriveMasterKey', () => {
    it('reproduce el vector estandar de PBKDF2-HMAC-SHA256', async () => {
        const clave = await deriveMasterKey('password', {
            algo: 'pbkdf2-sha256',
            iterations: 1,
            salt: enc.encode('salt'),
        });

        expect(hex(clave)).toBe('120fb6cffcf8b32c43e7225256c4f837a86548c92ccc35480805987cb70be17b');
    });

    it('es determinista: misma password y mismo salt dan la misma clave', async () => {
        const p = params();
        expect(hex(await deriveMasterKey('correcta', p))).toBe(hex(await deriveMasterKey('correcta', p)));
    });

    it('un salt distinto produce una clave distinta con la misma password', async () => {
        const a = await deriveMasterKey('correcta', params());
        const b = await deriveMasterKey('correcta', params());
        expect(hex(a)).not.toBe(hex(b));
    });

    it('una password distinta produce una clave distinta con el mismo salt', async () => {
        const p = params();
        expect(hex(await deriveMasterKey('correcta', p))).not.toBe(hex(await deriveMasterKey('incorrecta', p)));
    });

    it('devuelve 32 bytes', async () => {
        expect((await deriveMasterKey('x', params())).length).toBe(32);
    });
});

describe('subclaves', () => {
    it('el auth hash y la wrapping key son distintos entre si', async () => {
        const mk = await deriveMasterKey('x', params());
        expect(hex(await deriveAuthHash(mk))).not.toBe(hex(await deriveWrappingKey(mk)));
    });

    it('cada subclave es determinista respecto a la master key', async () => {
        const mk = await deriveMasterKey('x', params());
        expect(hex(await deriveAuthHash(mk))).toBe(hex(await deriveAuthHash(mk)));
        expect(hex(await deriveWrappingKey(mk))).toBe(hex(await deriveWrappingKey(mk)));
    });

    it('ambas devuelven 32 bytes', async () => {
        const mk = await deriveMasterKey('x', params());
        expect((await deriveAuthHash(mk)).length).toBe(32);
        expect((await deriveWrappingKey(mk)).length).toBe(32);
    });
});

describe('generateSalt', () => {
    it('devuelve 16 bytes distintos en cada llamada', () => {
        const a = generateSalt();
        const b = generateSalt();
        expect(a.length).toBe(16);
        expect(hex(a)).not.toBe(hex(b));
    });
});

describe('parametros por defecto', () => {
    it('usa 600000 iteraciones', () => {
        expect(DEFAULT_ITERATIONS).toBe(600000);
    });
});
