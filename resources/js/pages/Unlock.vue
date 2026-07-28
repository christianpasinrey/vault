<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSessionStore } from '@/stores/session';
import AuthShell from '@/components/ui/AuthShell.vue';
import AppButton from '@/components/ui/AppButton.vue';
import TextField from '@/components/ui/TextField.vue';
import Notice from '@/components/ui/Notice.vue';

const router = useRouter();
const session = useSessionStore();

const password = ref('');
const busy = ref(false);
const error = ref('');

async function submit(): Promise<void> {
    busy.value = true;
    error.value = '';

    try {
        await session.unlock(password.value);
        password.value = '';
        await router.push({ name: 'vault' });
    } catch {
        // The only thing that can fail here is the decryption itself, and it
        // fails identically for a typo and for a tampered blob.
        error.value = 'That is not the master password for this vault.';
    } finally {
        busy.value = false;
    }
}

async function signOut(): Promise<void> {
    await session.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <AuthShell title="Locked" lead="Your session is still open. The vault key was dropped from memory and has to be rebuilt from your master password.">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <TextField
                v-model="password"
                label="Master password"
                type="password"
                autocomplete="current-password"
                autofocus
                mono
                :hint="session.email"
            />

            <Notice v-if="error" tone="alarm">{{ error }}</Notice>

            <AppButton type="submit" variant="primary" :busy="busy" :disabled="password === ''">
                {{ busy ? 'Unlocking…' : 'Unlock' }}
            </AppButton>
        </form>

        <button type="button" class="self-start text-sm text-haze underline underline-offset-4 hover:text-paper" @click="signOut">
            Sign out instead
        </button>
    </AuthShell>
</template>
