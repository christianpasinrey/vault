import { ref, onMounted, onBeforeUnmount, type Ref } from 'vue';

/** How long before the deadline the user gets told the vault is about to lock. */
export const WARNING_SECONDS = 30;

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'] as const;

/**
 * Locks the vault after a stretch of inactivity.
 *
 * An unattended screen with the Vault Key in memory is the most realistic way
 * this system gets compromised, so the timer is not optional — it can only be
 * made longer or shorter.
 */
export function useAutoLock(timeoutSeconds: Ref<number>, lock: () => void) {
    const secondsLeft = ref(timeoutSeconds.value);
    const warning = ref(false);

    let deadline = 0;
    let ticker: ReturnType<typeof setInterval> | null = null;

    function postpone(): void {
        deadline = Date.now() + timeoutSeconds.value * 1000;
        secondsLeft.value = timeoutSeconds.value;
        warning.value = false;
    }

    function tick(): void {
        secondsLeft.value = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        warning.value = secondsLeft.value <= WARNING_SECONDS;

        if (secondsLeft.value === 0) {
            stop();
            lock();
        }
    }

    function onActivity(): void {
        // While the warning is up, deliberate action is needed to stay unlocked:
        // a stray mouse nudge should not silently keep the vault open.
        if (!warning.value) postpone();
    }

    function onVisibilityChange(): void {
        if (document.visibilityState === 'visible') onActivity();
    }

    function start(): void {
        postpone();
        ticker ??= setInterval(tick, 1000);
    }

    function stop(): void {
        if (ticker !== null) clearInterval(ticker);
        ticker = null;
    }

    onMounted(() => {
        ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, onActivity, { passive: true }));
        document.addEventListener('visibilitychange', onVisibilityChange);
        start();
    });

    onBeforeUnmount(() => {
        ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, onActivity));
        document.removeEventListener('visibilitychange', onVisibilityChange);
        stop();
    });

    return { secondsLeft, warning, postpone, stop };
}
