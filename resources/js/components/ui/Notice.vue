<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'alarm' | 'brass' | 'quiet' }>(), { tone: 'quiet' });
</script>

<template>
    <div
        class="border-l-2 py-2 pl-3 text-sm leading-relaxed"
        :class="{
            'border-alarm bg-alarm/8 text-paper': tone === 'alarm',
            'border-brass bg-brass/8 text-paper': tone === 'brass',
            'border-line bg-panel text-haze': tone === 'quiet',
        }"
        :role="tone === 'alarm' ? 'alert' : undefined"
    >
        <slot />
    </div>
</template>
