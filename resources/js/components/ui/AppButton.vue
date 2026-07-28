<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'primary' | 'quiet' | 'danger';
        type?: 'button' | 'submit';
        disabled?: boolean;
        busy?: boolean;
    }>(),
    { variant: 'quiet', type: 'button', disabled: false, busy: false },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || busy"
        :aria-busy="busy"
        class="inline-flex items-center justify-center gap-2 rounded-sm border px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-45"
        :class="{
            'border-brass bg-brass text-ink hover:bg-brass/90': variant === 'primary',
            'border-line bg-raised text-paper hover:border-haze/60': variant === 'quiet',
            'border-alarm/50 bg-transparent text-alarm hover:bg-alarm/10': variant === 'danger',
        }"
    >
        <slot />
    </button>
</template>
