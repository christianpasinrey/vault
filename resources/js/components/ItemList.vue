<script setup lang="ts">
import type { DecryptedItem } from '@/types/item';
import { TYPE_LABEL } from '@/types/item';
import { expiryStatus } from '@/lib/expiry';

defineProps<{ items: DecryptedItem[]; selectedId: string | null }>();

const emit = defineEmits<{ select: [item: DecryptedItem] }>();

function subtitle(item: DecryptedItem): string {
    return item.fields.username || item.fields.service || item.fields.url || TYPE_LABEL[item.type];
}

function isProduction(item: DecryptedItem): boolean {
    return (item.fields.environment ?? '').trim().toLowerCase() === 'prod';
}
</script>

<template>
    <ul class="flex flex-col divide-y divide-line">
        <li v-for="item in items" :key="item.id">
            <button
                type="button"
                class="flex w-full flex-col gap-1 px-4 py-3 text-left"
                :class="selectedId === item.id ? 'bg-raised' : 'hover:bg-panel'"
                :aria-current="selectedId === item.id"
                @click="emit('select', item)"
            >
                <div class="flex items-center gap-2">
                    <span v-if="item.favorite" class="text-brass" aria-label="Favourite">★</span>
                    <span class="min-w-0 flex-1 truncate text-sm text-paper">{{ item.name }}</span>

                    <span
                        v-if="isProduction(item)"
                        class="shrink-0 rounded-sm border border-alarm/50 px-1.5 py-0.5 font-mono text-[0.6rem] tracking-widest text-alarm uppercase"
                    >
                        prod
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="min-w-0 flex-1 truncate font-mono text-xs text-haze">{{ subtitle(item) }}</span>

                    <span
                        v-if="expiryStatus(item.fields.expires_at)?.expired"
                        class="shrink-0 font-mono text-[0.65rem] text-alarm"
                    >
                        expired
                    </span>
                    <span
                        v-else-if="expiryStatus(item.fields.expires_at)?.soon"
                        class="shrink-0 font-mono text-[0.65rem] text-brass"
                    >
                        expiring
                    </span>
                </div>
            </button>
        </li>
    </ul>
</template>
