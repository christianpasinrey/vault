<script setup lang="ts">
import { useId } from 'vue';

withDefaults(
    defineProps<{
        label: string;
        type?: string;
        hint?: string;
        error?: string;
        /** Machine material — keys, passwords, tokens — is always set in mono. */
        mono?: boolean;
        autocomplete?: string;
        autofocus?: boolean;
        placeholder?: string;
    }>(),
    { type: 'text', mono: false, autocomplete: 'off' },
);

const model = defineModel<string>({ required: true });
const id = useId();
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-xs font-medium tracking-wide text-haze uppercase">{{ label }}</label>

        <input
            :id="id"
            v-model="model"
            :type="type"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :placeholder="placeholder"
            :aria-invalid="error !== undefined && error !== ''"
            :aria-describedby="hint || error ? `${id}-note` : undefined"
            spellcheck="false"
            class="rounded-sm border bg-ink px-3 py-2 text-paper placeholder:text-haze/40"
            :class="[error ? 'border-alarm' : 'border-line focus:border-brass', mono ? 'font-mono text-sm' : '']"
        />

        <p v-if="error" :id="`${id}-note`" class="text-xs text-alarm">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-note`" class="text-xs text-haze">{{ hint }}</p>
    </div>
</template>
