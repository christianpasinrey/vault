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

const params = (overrides: Partial<KdfParams> = {}): KdfParams => ({
    algo: 'pbkdf2-sha256',
    iterations: 1000,
    salt: generateSalt(),
    ...overrides,
});

describe('deriveMasterKey', () => {
    it('reproduces the standard PBKDF2-HMAC-SHA256 test vector', async () => {
        const key = await deriveMasterKey('password', {
            algo: 'pbkdf2-sha256',
            iterations: 1,
            salt: enc.encode('salt'),
        });

        expect(hex(key)).toBe('120fb6cffcf8b32c43e7225256c4f837a86548c92ccc35480805987cb70be17b');
    });

    it('is deterministic: same password and salt give the same key', async () => {
        const p = params();
        expect(hex(await deriveMasterKey('correct', p))).toBe(hex(await deriveMasterKey('correct', p)));
    });

    it('produces a different key for a different salt with the same password', async () => {
        const a = await deriveMasterKey('correct', params());
        const b = await deriveMasterKey('correct', params());
        expect(hex(a)).not.toBe(hex(b));
    });

    it('produces a different key for a different password with the same salt', async () => {
        const p = params();
        expect(hex(await deriveMasterKey('correct', p))).not.toBe(hex(await deriveMasterKey('wrong', p)));
    });

    it('returns 32 bytes', async () => {
        expect((await deriveMasterKey('x', params())).length).toBe(32);
    });
});

describe('subkeys', () => {
    it('derives an auth hash and a wrapping key that differ from each other', async () => {
        const mk = await deriveMasterKey('x', params());
        expect(hex(await deriveAuthHash(mk))).not.toBe(hex(await deriveWrappingKey(mk)));
    });

    it('derives each subkey deterministically from the master key', async () => {
        const mk = await deriveMasterKey('x', params());
        expect(hex(await deriveAuthHash(mk))).toBe(hex(await deriveAuthHash(mk)));
        expect(hex(await deriveWrappingKey(mk))).toBe(hex(await deriveWrappingKey(mk)));
    });

    it('returns 32 bytes from both', async () => {
        const mk = await deriveMasterKey('x', params());
        expect((await deriveAuthHash(mk)).length).toBe(32);
        expect((await deriveWrappingKey(mk)).length).toBe(32);
    });
});

describe('generateSalt', () => {
    it('returns 16 distinct bytes on every call', () => {
        const a = generateSalt();
        const b = generateSalt();
        expect(a.length).toBe(16);
        expect(hex(a)).not.toBe(hex(b));
    });
});

describe('defaults', () => {
    it('uses 600000 iterations', () => {
        expect(DEFAULT_ITERATIONS).toBe(600000);
    });
});
