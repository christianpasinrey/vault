import { ref, getCurrentInstance, onBeforeUnmount } from 'vue';

/**
 * How long a secret is allowed to sit in the system clipboard.
 *
 * Long enough to switch windows and paste, short enough that it is gone before
 * the next thing you copy somewhere else has a chance to expose it.
 */
export const CLEAR_AFTER_SECONDS = 25;

export function useClipboard() {
    /** Which field is currently on the clipboard, for showing feedback next to it. */
    const copied = ref<string | null>(null);
    const secondsLeft = ref(0);
    const error = ref('');

    let clearTimer: ReturnType<typeof setTimeout> | null = null;
    let ticker: ReturnType<typeof setInterval> | null = null;

    async function copy(text: string, label = ''): Promise<void> {
        error.value = '';

        try {
            await navigator.clipboard.writeText(text);
        } catch {
            // Browsers refuse outside a user gesture or a secure context, and the
            // user needs to know the secret is not where they think it is.
            error.value = 'The browser would not let this be written to the clipboard.';

            return;
        }

        // Whatever was queued belonged to the previous secret; wiping now would
        // cut this one short.
        cancel();

        copied.value = label;
        secondsLeft.value = CLEAR_AFTER_SECONDS;

        ticker = setInterval(() => {
            secondsLeft.value = Math.max(0, secondsLeft.value - 1);
        }, 1000);

        clearTimer = setTimeout(() => void wipe(), CLEAR_AFTER_SECONDS * 1000);
    }

    async function wipe(): Promise<void> {
        cancel();
        copied.value = null;
        secondsLeft.value = 0;

        try {
            await navigator.clipboard.writeText('');
        } catch {
            // Nothing to tell the user here: they have moved on long ago.
        }
    }

    function cancel(): void {
        if (clearTimer !== null) clearTimeout(clearTimer);
        if (ticker !== null) clearInterval(ticker);
        clearTimer = null;
        ticker = null;
    }

    // Leaving the screen must not leave a secret behind on the clipboard.
    if (getCurrentInstance() !== null) onBeforeUnmount(() => void wipe());

    return { copy, wipe, copied, secondsLeft, error };
}
