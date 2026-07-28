<script setup lang="ts">
import { ref, computed, watch } from 'vue';

const props = defineProps<{
    label: string;
    value: string;
    /** Secret fields stay sealed until asked for; the rest are shown outright. */
    secret: boolean;
    /** Long free text gets its own block instead of a single line. */
    block?: boolean;
    copiedLabel: string | null;
    secondsLeft: number;
}>();

const emit = defineEmits<{ copy: [value: string, label: string] }>();

const revealed = ref(false);
const isCopied = computed(() => props.copiedLabel === props.label);

// Moving to another item must not carry a revealed secret across.
watch(() => props.value, () => (revealed.value = false));

const shown = computed(() => (!props.secret || revealed.value ? props.value : ''));
</script>

<template>
    <div class="flex flex-col gap-1.5 border-b border-line py-3 last:border-b-0">
        <div class="flex items-center gap-3">
            <span class="text-xs font-medium tracking-wide text-haze uppercase">{{ label }}</span>

            <span v-if="isCopied" class="ml-auto font-mono text-xs tabular-nums text-brass">
                copied · clears in {{ secondsLeft }}s
            </span>
        </div>

        <div class="flex items-start gap-3">
            <!-- Sealed state: fixed bars, never a run of asterisks that would
                 leak the length to anyone looking over your shoulder. -->
            <span v-if="secret && !revealed" class="seal flex-1" :aria-label="`${label}, hidden`">
                <span v-for="bar in 16" :key="bar" />
            </span>

            <pre
                v-else-if="block"
                class="max-h-64 flex-1 overflow-auto rounded-sm bg-ink px-3 py-2 font-mono text-sm break-words whitespace-pre-wrap text-paper"
            >{{ shown }}</pre>

            <span v-else class="flex-1 font-mono text-sm break-all text-paper">{{ shown || '—' }}</span>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    v-if="secret"
                    type="button"
                    class="rounded-sm px-2 py-1 text-xs text-haze hover:bg-raised hover:text-paper"
                    :aria-pressed="revealed"
                    @click="revealed = !revealed"
                >
                    {{ revealed ? 'Hide' : 'Reveal' }}
                </button>

                <button
                    v-if="value !== ''"
                    type="button"
                    class="rounded-sm px-2 py-1 text-xs text-haze hover:bg-raised hover:text-paper"
                    @click="emit('copy', value, label)"
                >
                    Copy
                </button>
            </div>
        </div>
    </div>
</template>
