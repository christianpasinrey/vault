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

const email = ref('');
const password = ref('');
const busy = ref(false);
const error = ref('');
const step = ref<'credentials' | 'passkey'>('credentials');

async function submit(): Promise<void> {
    busy.value = true;
    error.value = '';
    step.value = 'passkey';

    try {
        await session.login(email.value.trim(), password.value);
        password.value = '';
        await router.push({ name: 'vault' });
    } catch (failure) {
        step.value = 'credentials';
        error.value =
            failure instanceof Error && failure.name === 'NotAllowedError'
                ? 'The passkey prompt was dismissed.'
                : ((failure as Error).message ?? 'Sign-in failed.');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthShell title="Sign in" lead="Master password first, then your passkey.">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <TextField v-model="email" label="Email" type="email" autocomplete="username" autofocus />

            <TextField
                v-model="password"
                label="Master password"
                type="password"
                autocomplete="current-password"
                mono
            />

            <Notice v-if="busy && step === 'passkey'" tone="brass">Waiting for your passkey…</Notice>
            <Notice v-if="error" tone="alarm">{{ error }}</Notice>

            <AppButton type="submit" variant="primary" :busy="busy" :disabled="email === '' || password === ''">
                {{ busy ? 'Signing in…' : 'Sign in' }}
            </AppButton>
        </form>
    </AuthShell>
</template>
