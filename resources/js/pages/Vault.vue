<script setup lang="ts">
import { onMounted } from 'vue';
import { useVaultStore } from '@/stores/vault';

const vault = useVaultStore();

onMounted(() => vault.load());
</script>

<template>
    <main class="px-5 py-8">
        <p class="text-sm text-haze">{{ vault.live.length }} items.</p>
    </main>
</template>
