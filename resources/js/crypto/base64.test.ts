import { describe, it, expect } from 'vitest';
import { bytesToBase64, base64ToBytes } from './base64';

describe('base64', () => {
    it('encodes known bytes to base64', () => {
        expect(bytesToBase64(new Uint8Array([72, 101, 108, 108, 111]))).toBe('SGVsbG8=');
    });

    it('decodes known base64 to bytes', () => {
        expect(Array.from(base64ToBytes('SGVsbG8='))).toEqual([72, 101, 108, 108, 111]);
    });

    it('survives a round trip with arbitrary binary bytes', () => {
        const original = crypto.getRandomValues(new Uint8Array(64));
        expect(Array.from(base64ToBytes(bytesToBase64(original)))).toEqual(Array.from(original));
    });

    it('handles the empty array', () => {
        expect(bytesToBase64(new Uint8Array(0))).toBe('');
        expect(base64ToBytes('').length).toBe(0);
    });
});
