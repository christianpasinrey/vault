/**
 * Password generation.
 *
 * Every character comes from crypto.getRandomValues, and the mapping onto the
 * alphabet rejects biased samples instead of taking a modulo — a modulo would
 * quietly make the first characters of the alphabet more likely, which is
 * exactly the kind of flaw that never shows up in casual use.
 */

export const MIN_LENGTH = 8;

/** Characters that get misread when a password is copied off a screen by hand. */
const AMBIGUOUS = /[0O1lI|]/;

const CLASSES = {
    lower: 'abcdefghijklmnopqrstuvwxyz',
    upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    digits: '0123456789',
    symbols: '!#$%&()*+,-./:;<=>?@[]^_{|}~',
} as const;

export interface PasswordOptions {
    length: number;
    symbols: boolean;
    digits: boolean;
    excludeAmbiguous: boolean;
}

/** A uniform integer in [0, bound). Samples that would skew the result are discarded. */
function randomBelow(bound: number): number {
    // The largest multiple of `bound` that fits in a byte; anything above it
    // would map unevenly.
    const limit = Math.floor(256 / bound) * bound;
    const buffer = new Uint8Array(1);

    for (;;) {
        crypto.getRandomValues(buffer);

        if (buffer[0]! < limit) return buffer[0]! % bound;
    }
}

function shuffle(characters: string[]): string[] {
    for (let i = characters.length - 1; i > 0; i--) {
        const j = randomBelow(i + 1);
        [characters[i], characters[j]] = [characters[j]!, characters[i]!];
    }

    return characters;
}

export function generatePassword(options: PasswordOptions): string {
    if (options.length < MIN_LENGTH) {
        throw new Error(`A password shorter than ${MIN_LENGTH} characters is not worth generating.`);
    }

    const filter = (alphabet: string) =>
        options.excludeAmbiguous
            ? [...alphabet].filter((character) => !AMBIGUOUS.test(character)).join('')
            : alphabet;

    const selected: string[] = [CLASSES.lower, CLASSES.upper];
    if (options.digits) selected.push(CLASSES.digits);
    if (options.symbols) selected.push(CLASSES.symbols);

    const enabled = selected.map(filter).filter((alphabet) => alphabet.length > 0);

    const pool = enabled.join('');

    // One character from each enabled class first, so "include digits" is a
    // guarantee rather than a probability.
    const characters = enabled.map((alphabet) => alphabet[randomBelow(alphabet.length)]!);

    while (characters.length < options.length) {
        characters.push(pool[randomBelow(pool.length)]!);
    }

    return shuffle(characters).join('');
}
