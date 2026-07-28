<script setup lang="ts">
defineProps<{ title: string; lead?: string }>();
</script>

<template>
    <main class="mx-auto flex min-h-dvh w-full max-w-lg flex-col justify-center gap-8 px-6 py-16">
        <header class="flex flex-col gap-3">
            <!-- The wordmark is set in mono: this is an instrument, not a brand. -->
            <p class="font-mono text-xs tracking-[0.35em] text-brass uppercase">Vault</p>
            <h1 class="text-3xl font-semibold tracking-tight text-paper">{{ title }}</h1>
            <p v-if="lead" class="text-sm leading-relaxed text-haze">{{ lead }}</p>
        </header>

        <slot />
    </main>
</template>
