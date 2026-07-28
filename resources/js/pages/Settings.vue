<script setup lang="ts">
import { ref, computed } from 'vue';
import { api } from '@/api/client';
import { useSessionStore, MIN_MASTER_PASSWORD_LENGTH } from '@/stores/session';
import { useVaultStore } from '@/stores/vault';
import type { EncryptedItem } from '@/types/item';
import PasskeyList from '@/components/PasskeyList.vue';
import AppButton from '@/components/ui/AppButton.vue';
import TextField from '@/components/ui/TextField.vue';
import Notice from '@/components/ui/Notice.vue';

const session = useSessionStore();
const vault = useVaultStore();

// --- Master password -------------------------------------------------------

const currentPassword = ref('');
const newPassword = ref('');
const confirmation = ref('');
const rotating = ref(false);
const rotationError = ref('');
const rotationDone = ref(false);

const canRotate = computed(
    () =>
        currentPassword.value !== '' &&
        newPassword.value.length >= MIN_MASTER_PASSWORD_LENGTH &&
        newPassword.value === confirmation.value,
);

async function rotate(): Promise<void> {
    rotating.value = true;
    rotationError.value = '';
    rotationDone.value = false;

    try {
        await session.rotateMasterPassword(currentPassword.value, newPassword.value);
        currentPassword.value = '';
        newPassword.value = '';
        confirmation.value = '';
        rotationDone.value = true;
    } catch (failure) {
        rotationError.value = (failure as Error).message ?? 'The master password could not be changed.';
    } finally {
        rotating.value = false;
    }
}

// --- Auto-lock -------------------------------------------------------------

const lockMinutes = ref(Math.round(session.autoLockSeconds / 60));
const lockSaved = ref(false);
const lockError = ref('');

async function saveAutoLock(): Promise<void> {
    lockSaved.value = false;
    lockError.value = '';

    try {
        const seconds = Math.min(3600, Math.max(60, Math.round(lockMinutes.value * 60)));
        await api.put('/api/account/settings', { auto_lock_seconds: seconds });
        session.setAutoLockSeconds(seconds);
        lockMinutes.value = seconds / 60;
        lockSaved.value = true;
    } catch (failure) {
        lockError.value = (failure as Error).message ?? 'The lock timer could not be saved.';
    }
}

// --- Export and import -----------------------------------------------------

const importing = ref(false);
const transferMessage = ref('');
const transferError = ref('');

async function exportVault(): Promise<void> {
    transferError.value = '';
    transferMessage.value = '';

    const payload = await api.get<unknown>('/api/vault/export');

    // The file is as opaque as the database: it is the ciphertext, unchanged.
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `vault-export-${new Date().toISOString().slice(0, 10)}.json`;
    link.click();
    URL.revokeObjectURL(url);

    transferMessage.value = 'Exported. The file is encrypted with your Vault Key and useless without it.';
}

async function importVault(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file === undefined) return;

    importing.value = true;
    transferError.value = '';
    transferMessage.value = '';

    try {
        const parsed = JSON.parse(await file.text()) as { format?: string; items?: EncryptedItem[] };

        if (parsed.format !== 'vault-export-v1' || !Array.isArray(parsed.items)) {
            throw new Error('That file is not a Vault export.');
        }

        const { added, skipped } = await vault.importItems(parsed.items);
        transferMessage.value = `Imported ${added} items. ${skipped} were already here.`;
    } catch (failure) {
        transferError.value =
            failure instanceof SyntaxError
                ? 'That file is not readable JSON.'
                : ((failure as Error).message ??
                  'Nothing was imported: this vault key does not decrypt that file.');
    } finally {
        importing.value = false;
        input.value = '';
    }
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-2xl flex-col gap-12 px-5 py-8">
        <section class="flex flex-col gap-4">
            <header>
                <h2 class="text-lg font-semibold tracking-tight">Passkeys</h2>
                <p class="mt-1 text-sm text-haze">Required on every sign-in. Keep at least two.</p>
            </header>

            <PasskeyList />
        </section>

        <section class="flex flex-col gap-4">
            <header>
                <h2 class="text-lg font-semibold tracking-tight">Master password</h2>
                <p class="mt-1 text-sm text-haze">
                    Your items are not re-encrypted: the vault key stays the same and is simply re-wrapped with the new
                    password.
                </p>
            </header>

            <TextField v-model="currentPassword" label="Current master password" type="password" mono />
            <TextField
                v-model="newPassword"
                label="New master password"
                type="password"
                mono
                :hint="`At least ${MIN_MASTER_PASSWORD_LENGTH} characters.`"
            />
            <TextField v-model="confirmation" label="New master password again" type="password" mono />

            <Notice v-if="rotationError" tone="alarm">{{ rotationError }}</Notice>
            <Notice v-else-if="rotationDone" tone="brass">
                Changed. Use the new password from now on — the old one no longer opens anything.
            </Notice>

            <AppButton variant="primary" :disabled="!canRotate" :busy="rotating" @click="rotate">
                Change master password
            </AppButton>
        </section>

        <section class="flex flex-col gap-4">
            <header>
                <h2 class="text-lg font-semibold tracking-tight">Automatic lock</h2>
                <p class="mt-1 text-sm text-haze">How long the vault stays open with nobody touching it.</p>
            </header>

            <div class="flex items-end gap-3">
                <label class="flex flex-1 items-center gap-3 text-sm text-haze">
                    <input v-model.number="lockMinutes" type="range" min="1" max="60" class="flex-1 accent-brass" />
                    <span class="w-16 font-mono text-xs tabular-nums">{{ lockMinutes }} min</span>
                </label>

                <AppButton @click="saveAutoLock">Save</AppButton>
            </div>

            <Notice v-if="lockError" tone="alarm">{{ lockError }}</Notice>
            <Notice v-else-if="lockSaved" tone="brass">Saved.</Notice>
        </section>

        <section class="flex flex-col gap-4">
            <header>
                <h2 class="text-lg font-semibold tracking-tight">Export and import</h2>
                <p class="mt-1 text-sm text-haze">
                    An export is a copy of the ciphertext. It can only be imported back into a vault holding this same
                    vault key.
                </p>
            </header>

            <div class="flex flex-wrap items-center gap-3">
                <AppButton @click="exportVault">Export the vault</AppButton>

                <label
                    class="inline-flex cursor-pointer items-center rounded-sm border border-line bg-raised px-4 py-2 text-sm font-medium hover:border-haze/60"
                >
                    {{ importing ? 'Importing…' : 'Import a file' }}
                    <input type="file" accept="application/json" class="sr-only" @change="importVault" />
                </label>
            </div>

            <Notice v-if="transferError" tone="alarm">{{ transferError }}</Notice>
            <Notice v-else-if="transferMessage" tone="quiet">{{ transferMessage }}</Notice>
        </section>
    </main>
</template>
