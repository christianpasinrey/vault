<script setup lang="ts">
import { computed } from 'vue';
import type { DecryptedItem, ItemType } from '@/types/item';
import { TYPE_LABEL } from '@/types/item';

const props = defineProps<{ items: DecryptedItem[] }>();

const type = defineModel<ItemType | null>('type', { required: true });
const folder = defineModel<string | null>('folder', { required: true });
const favorites = defineModel<boolean>('favorites', { required: true });

const types = computed(() =>
    (Object.keys(TYPE_LABEL) as ItemType[]).map((value) => ({
        value,
        label: TYPE_LABEL[value],
        count: props.items.filter((item) => item.type === value).length,
    })),
);

const folders = computed(() => {
    const counts = new Map<string, number>();

    props.items.forEach((item) => {
        if (item.folder !== '') counts.set(item.folder, (counts.get(item.folder) ?? 0) + 1);
    });

    return [...counts.entries()].sort((a, b) => a[0].localeCompare(b[0]));
});

const favoriteCount = computed(() => props.items.filter((item) => item.favorite).length);

function clearFilters(): void {
    type.value = null;
    folder.value = null;
    favorites.value = false;
}
</script>

<template>
    <aside class="flex w-full shrink-0 flex-col gap-6 border-line p-4 lg:w-56 lg:border-r">
        <section class="flex flex-col gap-1">
            <button
                type="button"
                class="flex items-center justify-between rounded-sm px-2 py-1.5 text-left text-sm"
                :class="type === null && folder === null && !favorites ? 'bg-raised text-paper' : 'text-haze hover:text-paper'"
                @click="clearFilters"
            >
                <span>Everything</span>
                <span class="font-mono text-xs tabular-nums text-haze">{{ items.length }}</span>
            </button>

            <button
                type="button"
                class="flex items-center justify-between rounded-sm px-2 py-1.5 text-left text-sm"
                :class="favorites ? 'bg-raised text-paper' : 'text-haze hover:text-paper'"
                @click="favorites = !favorites"
            >
                <span>Favourites</span>
                <span class="font-mono text-xs tabular-nums text-haze">{{ favoriteCount }}</span>
            </button>
        </section>

        <section class="flex flex-col gap-1">
            <h2 class="px-2 font-mono text-[0.65rem] tracking-[0.2em] text-haze uppercase">Type</h2>

            <button
                v-for="entry in types"
                :key="entry.value"
                type="button"
                class="flex items-center justify-between rounded-sm px-2 py-1.5 text-left text-sm"
                :class="type === entry.value ? 'bg-raised text-paper' : 'text-haze hover:text-paper'"
                @click="type = type === entry.value ? null : entry.value"
            >
                <span>{{ entry.label }}</span>
                <span class="font-mono text-xs tabular-nums text-haze">{{ entry.count }}</span>
            </button>
        </section>

        <section v-if="folders.length > 0" class="flex flex-col gap-1">
            <h2 class="px-2 font-mono text-[0.65rem] tracking-[0.2em] text-haze uppercase">Folder</h2>

            <button
                v-for="[name, count] in folders"
                :key="name"
                type="button"
                class="flex items-center justify-between rounded-sm px-2 py-1.5 text-left text-sm"
                :class="folder === name ? 'bg-raised text-paper' : 'text-haze hover:text-paper'"
                @click="folder = folder === name ? null : name"
            >
                <span class="truncate">{{ name }}</span>
                <span class="ml-2 font-mono text-xs tabular-nums text-haze">{{ count }}</span>
            </button>
        </section>
    </aside>
</template>
