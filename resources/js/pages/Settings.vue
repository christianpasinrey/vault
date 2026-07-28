<script setup lang="ts">
import PasskeyList from '@/components/PasskeyList.vue';
</script>

<template>
    <main class="mx-auto flex w-full max-w-2xl flex-col gap-8 px-5 py-8">
        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-semibold tracking-tight">Passkeys</h2>
            <PasskeyList />
        </section>
    </main>
</template>
