import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { useClipboard, CLEAR_AFTER_SECONDS } from './useClipboard';

const written: string[] = [];

beforeEach(() => {
    vi.useFakeTimers();
    written.length = 0;

    vi.stubGlobal('navigator', {
        clipboard: {
            writeText: (text: string) => {
                written.push(text);

                return Promise.resolve();
            },
        },
    });
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('useClipboard', () => {
    it('writes the text to the clipboard', async () => {
        const { copy } = useClipboard();

        await copy('hunter2');

        expect(written).toEqual(['hunter2']);
    });

    it('wipes the clipboard once the countdown runs out', async () => {
        const { copy } = useClipboard();

        await copy('hunter2');
        await vi.advanceTimersByTimeAsync(CLEAR_AFTER_SECONDS * 1000);

        expect(written).toEqual(['hunter2', '']);
    });

    it('does not wipe it early', async () => {
        const { copy } = useClipboard();

        await copy('hunter2');
        await vi.advanceTimersByTimeAsync((CLEAR_AFTER_SECONDS - 1) * 1000);

        expect(written).toEqual(['hunter2']);
    });

    it('cancels the pending wipe when something new is copied', async () => {
        const { copy } = useClipboard();

        await copy('first');
        await vi.advanceTimersByTimeAsync(20_000);
        await copy('second');

        // The first wipe must not fire and cut the second copy short.
        await vi.advanceTimersByTimeAsync(10_000);
        expect(written).toEqual(['first', 'second']);

        await vi.advanceTimersByTimeAsync((CLEAR_AFTER_SECONDS - 10) * 1000);
        expect(written).toEqual(['first', 'second', '']);
    });

    it('counts down the seconds left and reports what was copied', async () => {
        const { copy, secondsLeft, copied } = useClipboard();

        await copy('hunter2', 'password');

        expect(copied.value).toBe('password');
        expect(secondsLeft.value).toBe(CLEAR_AFTER_SECONDS);

        await vi.advanceTimersByTimeAsync(5000);
        expect(secondsLeft.value).toBe(CLEAR_AFTER_SECONDS - 5);

        await vi.advanceTimersByTimeAsync((CLEAR_AFTER_SECONDS - 5) * 1000);
        expect(secondsLeft.value).toBe(0);
        expect(copied.value).toBeNull();
    });

    it('reports a clipboard the browser refuses to write to', async () => {
        vi.stubGlobal('navigator', {
            clipboard: {
                writeText: () => Promise.reject(new Error('denied')),
            },
        });

        const { copy, error } = useClipboard();
        await copy('hunter2');

        expect(error.value).toMatch(/clipboard/i);
    });
});
