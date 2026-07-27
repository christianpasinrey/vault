import { describe, it, expect } from 'vitest';
import { bytesToBase64, base64ToBytes } from './base64';

describe('base64', () => {
    it('convierte bytes conocidos a base64', () => {
        expect(bytesToBase64(new Uint8Array([72, 111, 108, 97]))).toBe('SG9sYQ==');
    });

    it('descodifica base64 conocido a bytes', () => {
        expect(Array.from(base64ToBytes('SG9sYQ=='))).toEqual([72, 111, 108, 97]);
    });

    it('sobrevive a la ida y vuelta con bytes binarios arbitrarios', () => {
        const original = crypto.getRandomValues(new Uint8Array(64));
        expect(Array.from(base64ToBytes(bytesToBase64(original)))).toEqual(Array.from(original));
    });

    it('maneja el array vacio', () => {
        expect(bytesToBase64(new Uint8Array(0))).toBe('');
        expect(base64ToBytes('').length).toBe(0);
    });
});
