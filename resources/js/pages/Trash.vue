<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useVaultStore } from '@/stores/vault';
import { TYPE_LABEL } from '@/types/item';
import AppButton from '@/components/ui/AppButton.vue';
import Notice from '@/components/ui/Notice.vue';

/** Must match `VaultPurge::GRACE_DAYS` on the server. */
const GRACE_DAYS = 30;

const vault = useVaultStore();
const error = ref('');

onMounted(async () => {
    try {
        await vault.load();
    } catch {
        error.value = 'The trash could not be loaded.';
    }
});

function daysLeft(deletedAt: string | null): number {
    if (deletedAt === null) return GRACE_DAYS;

    const elapsed = (Date.now() - new Date(deletedAt).getTime()) / 86_400_000;

    return Math.max(0, Math.ceil(GRACE_DAYS - elapsed));
}

async function restore(id: string): Promise<void> {
    error.value = '';

    try {
        await vault.restore(id);
    } catch {
        error.value = 'That item could not be restored.';
    }
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-5 py-8">
        <header class="flex flex-col gap-2">
            <h1 class="text-xl font-semibold tracking-tight">Trash</h1>
            <p class="text-sm text-haze">
                Deleted items are destroyed for good {{ GRACE_DAYS }} days after they land here. Nothing is recoverable
                afterwards.
            </p>
        </header>

        <Notice v-if="error" tone="alarm">{{ error }}</Notice>

        <ul v-if="vault.trashed.length > 0" class="flex flex-col divide-y divide-line border border-line">
            <li v-for="item in vault.trashed" :key="item.id" class="flex items-center gap-4 bg-panel px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-paper">{{ item.name }}</p>
                    <p class="font-mono text-xs text-haze">{{ TYPE_LABEL[item.type] }}</p>
                </div>

                <p
                    class="shrink-0 font-mono text-xs tabular-nums"
                    :class="daysLeft(item.deleted_at) <= 7 ? 'text-alarm' : 'text-haze'"
                >
                    {{ daysLeft(item.deleted_at) }} days left
                </p>

                <AppButton @click="restore(item.id)">Restore</AppButton>
            </li>
        </ul>

        <p v-else class="text-sm text-haze">The trash is empty.</p>
    </main>
</template>
