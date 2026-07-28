import { describe, it, expect } from 'vitest';
import { generatePassword } from './generator';

const base = { length: 20, symbols: true, digits: true, excludeAmbiguous: false };

describe('generatePassword', () => {
    it('honours the requested length', () => {
        expect(generatePassword({ ...base, length: 32 })).toHaveLength(32);
    });

    it('does not repeat itself between calls', () => {
        const generated = new Set(Array.from({ length: 100 }, () => generatePassword(base)));

        expect(generated.size).toBe(100);
    });

    it('leaves symbols out when they are turned off', () => {
        for (let i = 0; i < 50; i++) {
            expect(generatePassword({ ...base, symbols: false })).toMatch(/^[a-zA-Z0-9]+$/);
        }
    });

    it('leaves digits out when they are turned off', () => {
        for (let i = 0; i < 50; i++) {
            expect(generatePassword({ ...base, digits: false })).not.toMatch(/[0-9]/);
        }
    });

    it('excludes ambiguous characters when asked', () => {
        for (let i = 0; i < 50; i++) {
            expect(generatePassword({ ...base, excludeAmbiguous: true })).not.toMatch(/[0O1lI|]/);
        }
    });

    it('includes at least one character of every enabled class', () => {
        for (let i = 0; i < 50; i++) {
            const password = generatePassword({ ...base, length: 12 });

            expect(password).toMatch(/[a-z]/);
            expect(password).toMatch(/[A-Z]/);
            expect(password).toMatch(/[0-9]/);
        }
    });

    it('rejects lengths below 8', () => {
        expect(() => generatePassword({ ...base, length: 4 })).toThrow();
    });

    it('spreads characters across the alphabet rather than favouring the start', () => {
        // A modulo-based picker skews towards the first characters of the
        // alphabet. Over this many samples that skew would be unmistakable.
        const counts = new Map<string, number>();

        for (let i = 0; i < 400; i++) {
            for (const character of generatePassword({ ...base, length: 32, symbols: false, digits: false })) {
                counts.set(character, (counts.get(character) ?? 0) + 1);
            }
        }

        const frequencies = [...counts.values()];
        const expected = 400 * 32 / 52;

        expect(counts.size).toBe(52);
        expect(Math.min(...frequencies)).toBeGreaterThan(expected * 0.6);
        expect(Math.max(...frequencies)).toBeLessThan(expected * 1.4);
    });
});
