<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useVaultStore, ConflictError } from '@/stores/vault';
import type { DecryptedItem, ItemType, PlainItem } from '@/types/item';
import Sidebar from '@/components/Sidebar.vue';
import ItemList from '@/components/ItemList.vue';
import ItemDetail from '@/components/ItemDetail.vue';
import ItemForm from '@/components/ItemForm.vue';
import AppButton from '@/components/ui/AppButton.vue';
import Notice from '@/components/ui/Notice.vue';

const vault = useVaultStore();

const query = ref('');
const type = ref<ItemType | null>(null);
const folder = ref<string | null>(null);
const favourites = ref(false);
const selectedId = ref<string | null>(null);
const error = ref('');

/** null when nothing is being edited; `{ item: null }` while creating. */
const editing = ref<{ item: DecryptedItem | null } | null>(null);
const saving = ref(false);
const saveError = ref('');

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

const selected = computed(() => vault.live.find((item) => item.id === selectedId.value) ?? null);

function select(item: DecryptedItem): void {
    selectedId.value = item.id;
    editing.value = null;
}

function create(): void {
    saveError.value = '';
    editing.value = { item: null };
}

function edit(): void {
    saveError.value = '';
    editing.value = { item: selected.value };
}

async function save(plain: PlainItem): Promise<void> {
    if (editing.value === null) return;

    saving.value = true;
    saveError.value = '';

    try {
        const saved = await vault.save(plain, editing.value.item ?? undefined);
        selectedId.value = saved.id;
        editing.value = null;
    } catch (failure) {
        // On a conflict the server's copy is already back in the list; the draft
        // stays on screen so nothing the user typed is lost.
        saveError.value =
            failure instanceof ConflictError
                ? `${failure.message} The version now on the server is shown behind this form.`
                : ((failure as Error).message ?? 'The item could not be saved.');
    } finally {
        saving.value = false;
    }
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
            <div class="flex items-center gap-2 border-b border-line p-3">
                <input
                    v-model="query"
                    type="search"
                    placeholder="Search"
                    aria-label="Search items"
                    spellcheck="false"
                    class="min-w-0 flex-1 rounded-sm border border-line bg-ink px-3 py-2 text-sm text-paper placeholder:text-haze/50 focus:border-brass"
                />

                <AppButton variant="primary" @click="create">New</AppButton>
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

            <ItemForm
                v-if="editing"
                :key="editing.item?.id ?? 'new'"
                :item="editing.item"
                :folders="vault.folders"
                :busy="saving"
                :error="saveError"
                @save="save"
                @cancel="editing = null"
            />

            <ItemDetail v-else-if="selected" :key="selected.id" :item="selected" @edit="edit" @remove="remove" />

            <p v-else class="p-6 text-sm text-haze">Pick an item to see what is in it.</p>
        </div>
    </div>
</template>
