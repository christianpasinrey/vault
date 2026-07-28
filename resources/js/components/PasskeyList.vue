<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { api } from '@/api/client';
import { useSessionStore } from '@/stores/session';
import AppButton from '@/components/ui/AppButton.vue';
import TextField from '@/components/ui/TextField.vue';
import Notice from '@/components/ui/Notice.vue';

interface Passkey {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
}

const emit = defineEmits<{ count: [value: number] }>();

const session = useSessionStore();

const passkeys = ref<Passkey[]>([]);
const name = ref('');
const busy = ref(false);
const error = ref('');

onMounted(load);
watch(passkeys, (list) => emit('count', list.length), { deep: true });

async function load(): Promise<void> {
    passkeys.value = await api.get<Passkey[]>('/api/account/passkeys');
}

async function add(): Promise<void> {
    busy.value = true;
    error.value = '';

    try {
        await session.registerPasskey(name.value.trim() === '' ? defaultName() : name.value.trim());
        name.value = '';
        await load();
    } catch (failure) {
        error.value = message(failure);
    } finally {
        busy.value = false;
    }
}

async function remove(passkey: Passkey): Promise<void> {
    error.value = '';

    try {
        await api.del(`/api/account/passkeys/${passkey.id}`);
        await load();
    } catch (failure) {
        error.value = message(failure);
    }
}

function defaultName(): string {
    return `Passkey ${passkeys.value.length + 1}`;
}

function message(failure: unknown): string {
    // A cancelled or unsupported ceremony throws from the browser, not the API.
    if (failure instanceof Error && failure.name === 'NotAllowedError') return 'The passkey prompt was dismissed.';

    return failure instanceof Error ? failure.message : 'The passkey could not be registered.';
}

function formatDate(value: string | null): string {
    return value === null ? 'never used' : new Date(value).toLocaleDateString();
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <ul v-if="passkeys.length > 0" class="flex flex-col divide-y divide-line border border-line">
            <li v-for="passkey in passkeys" :key="passkey.id" class="flex items-center gap-3 bg-panel px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-paper">{{ passkey.name }}</p>
                    <p class="font-mono text-xs text-haze">{{ formatDate(passkey.last_used_at) }}</p>
                </div>

                <AppButton
                    v-if="passkeys.length > 1"
                    variant="danger"
                    @click="remove(passkey)"
                >
                    Remove
                </AppButton>
                <span v-else class="font-mono text-xs text-haze">last one</span>
            </li>
        </ul>

        <p v-else class="text-sm text-haze">No passkeys yet. Add one to be able to sign in.</p>

        <div class="flex items-end gap-3">
            <div class="flex-1">
                <TextField v-model="name" label="Name" :placeholder="defaultName()" />
            </div>
            <AppButton variant="primary" :busy="busy" @click="add">Add a passkey</AppButton>
        </div>

        <Notice v-if="error" tone="alarm">{{ error }}</Notice>
    </section>
</template>
