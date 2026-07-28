<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSessionStore, MIN_MASTER_PASSWORD_LENGTH } from '@/stores/session';
import AuthShell from '@/components/ui/AuthShell.vue';
import AppButton from '@/components/ui/AppButton.vue';
import TextField from '@/components/ui/TextField.vue';
import Notice from '@/components/ui/Notice.vue';
import PasskeyList from '@/components/PasskeyList.vue';

const route = useRoute();
const router = useRouter();
const session = useSessionStore();

const token = String(route.query.token ?? '');

const stage = ref<'password' | 'passkeys'>('password');
const password = ref('');
const confirmation = ref('');
const acknowledged = ref(false);
const busy = ref(false);
const error = ref('');
const passkeyCount = ref(0);

const tooShort = computed(() => password.value.length > 0 && password.value.length < MIN_MASTER_PASSWORD_LENGTH);
const mismatched = computed(() => confirmation.value.length > 0 && confirmation.value !== password.value);

const canSubmit = computed(
    () =>
        acknowledged.value &&
        password.value.length >= MIN_MASTER_PASSWORD_LENGTH &&
        confirmation.value === password.value,
);

async function submit(): Promise<void> {
    if (!canSubmit.value) return;

    busy.value = true;
    error.value = '';

    try {
        await session.completeSetup(token, password.value);
        password.value = '';
        confirmation.value = '';
        stage.value = 'passkeys';
    } catch (failure) {
        error.value = failure instanceof Error ? failure.message : 'The setup could not be completed.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthShell
        v-if="stage === 'password'"
        title="Choose your master password"
        lead="This is the only thing that decrypts your vault. It is never sent anywhere: the server stores a value derived from it that cannot be turned back into it."
    >
        <Notice v-if="token === ''" tone="alarm">
            This link has no setup token. Run <code class="font-mono">php artisan vault:init</code> on the server and
            open the link it prints.
        </Notice>

        <template v-else>
            <!-- The thesis of the whole product, stated before anything is typed. -->
            <section class="border border-alarm/40 bg-alarm/8 p-5">
                <h2 class="font-mono text-xs tracking-[0.2em] text-alarm uppercase">No recovery exists</h2>
                <p class="mt-3 text-sm leading-relaxed text-paper">
                    There is no reset email and no security question. The server holds nothing that can decrypt your
                    vault, so nobody — including you — can get in without this password.
                </p>
                <p class="mt-2 text-sm leading-relaxed text-paper">
                    Write it down and keep it somewhere outside this system before you continue.
                </p>
            </section>

            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <TextField
                    v-model="password"
                    label="Master password"
                    type="password"
                    autocomplete="new-password"
                    autofocus
                    mono
                    :hint="`At least ${MIN_MASTER_PASSWORD_LENGTH} characters. Length beats complexity.`"
                    :error="tooShort ? `Use at least ${MIN_MASTER_PASSWORD_LENGTH} characters.` : ''"
                />

                <TextField
                    v-model="confirmation"
                    label="Master password again"
                    type="password"
                    autocomplete="new-password"
                    mono
                    :error="mismatched ? 'The two entries do not match.' : ''"
                />

                <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-haze">
                    <input v-model="acknowledged" type="checkbox" class="mt-0.5 accent-brass" />
                    <span>I have stored this password somewhere safe. I understand it cannot be recovered.</span>
                </label>

                <Notice v-if="error" tone="alarm">{{ error }}</Notice>

                <AppButton type="submit" variant="primary" :disabled="!canSubmit" :busy="busy">
                    {{ busy ? 'Creating the vault…' : 'Create the vault' }}
                </AppButton>
            </form>
        </template>
    </AuthShell>

    <AuthShell
        v-else
        title="Add your passkeys"
        lead="A passkey is required on every sign-in, on top of the master password. Add two: if you lose the only one, the vault is gone."
    >
        <PasskeyList @count="(n) => (passkeyCount = n)" />

        <Notice v-if="passkeyCount === 1" tone="brass">
            One passkey is enough to sign in, but not enough to be safe. Add a second on a different device before you
            finish.
        </Notice>

        <AppButton
            variant="primary"
            :disabled="passkeyCount === 0"
            @click="router.push({ name: 'vault' })"
        >
            {{ passkeyCount < 2 ? 'Finish anyway' : 'Open the vault' }}
        </AppButton>
    </AuthShell>
</template>
