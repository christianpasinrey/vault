<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useVaultStore } from '@/stores/vault';
import type { DecryptedItem, ItemType } from '@/types/item';
import Sidebar from '@/components/Sidebar.vue';
import ItemList from '@/components/ItemList.vue';
import ItemDetail from '@/components/ItemDetail.vue';
import Notice from '@/components/ui/Notice.vue';

const vault = useVaultStore();

const query = ref('');
const type = ref<ItemType | null>(null);
const folder = ref<string | null>(null);
const favourites = ref(false);
const selectedId = ref<string | null>(null);
const error = ref('');

onMounted(async () => {
    try {
        await vault.load();
    } catch {
        error.value = 'The items could not be loaded.';
    }
});

const filtered = computed(() => {
    const scoped = vault.live.filter(
        (item) =>
            (type.value === null || item.type === type.value) &&
            (folder.value === null || item.folder === folder.value) &&
            (!favourites.value || item.favorite),
    );

    return vault.search(query.value, scoped);
});

const selected = computed(() => filtered.value.find((item) => item.id === selectedId.value) ?? null);

function select(item: DecryptedItem): void {
    selectedId.value = item.id;
}

async function remove(): Promise<void> {
    if (selected.value === null) return;

    const id = selected.value.id;
    selectedId.value = null;
    await vault.remove(id);
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
        <Sidebar v-model:type="type" v-model:folder="folder" v-model:favorites="favourites" :items="vault.live" />

        <section class="flex w-full shrink-0 flex-col border-line lg:w-80 lg:border-r">
            <div class="border-b border-line p-3">
                <input
                    v-model="query"
                    type="search"
                    placeholder="Search"
                    aria-label="Search items"
                    spellcheck="false"
                    class="w-full rounded-sm border border-line bg-ink px-3 py-2 text-sm text-paper placeholder:text-haze/50 focus:border-brass"
                />
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <ItemList :items="filtered" :selected-id="selectedId" @select="select" />

                <p v-if="filtered.length === 0 && !vault.loading" class="p-4 text-sm text-haze">
                    {{ vault.live.length === 0 ? 'This vault is empty.' : 'Nothing matches that.' }}
                </p>
            </div>
        </section>

        <div class="min-w-0 flex-1">
            <Notice v-if="error" tone="alarm">{{ error }}</Notice>

            <ItemDetail v-if="selected" :key="selected.id" :item="selected" @remove="remove" />

            <p v-else class="p-6 text-sm text-haze">Pick an item to see what is in it.</p>
        </div>
    </div>
</template>
