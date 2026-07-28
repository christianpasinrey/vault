<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useSessionStore } from '@/stores/session';
import { useVaultStore } from '@/stores/vault';
import { useAutoLock } from '@/composables/useAutoLock';
import AppButton from '@/components/ui/AppButton.vue';

const router = useRouter();
const session = useSessionStore();
const vault = useVaultStore();

const timeout = computed(() => session.autoLockSeconds);
const { secondsLeft, warning, postpone } = useAutoLock(timeout, lockNow);

const countdown = computed(() => {
    const minutes = Math.floor(secondsLeft.value / 60);
    const seconds = secondsLeft.value % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

function lockNow(): void {
    session.lock();
    vault.clear();
    void router.push({ name: 'unlock' });
}

async function signOut(): Promise<void> {
    vault.clear();
    await session.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <header class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-line bg-panel px-5 py-3">
            <RouterLink :to="{ name: 'vault' }" class="font-mono text-xs tracking-[0.35em] text-brass uppercase">
                Vault
            </RouterLink>

            <nav class="flex items-center gap-1 text-sm">
                <RouterLink
                    v-for="link in [
                        { name: 'vault', label: 'Items' },
                        { name: 'trash', label: 'Trash' },
                        { name: 'settings', label: 'Settings' },
                    ]"
                    :key="link.name"
                    :to="{ name: link.name }"
                    class="rounded-sm px-2.5 py-1 text-haze hover:text-paper"
                    active-class="bg-raised text-paper"
                >
                    {{ link.label }}
                </RouterLink>
            </nav>

            <div class="ml-auto flex items-center gap-3">
                <!-- The countdown is permanent chrome: the vault never locks as a surprise. -->
                <p
                    class="font-mono text-xs tabular-nums"
                    :class="warning ? 'text-alarm' : 'text-haze'"
                    :aria-live="warning ? 'polite' : 'off'"
                >
                    <span v-if="warning">Locking in {{ countdown }}</span>
                    <span v-else>Locks in {{ countdown }}</span>
                </p>

                <AppButton v-if="warning" @click="postpone">Stay unlocked</AppButton>
                <AppButton @click="lockNow">Lock</AppButton>
                <AppButton @click="signOut">Sign out</AppButton>
            </div>
        </header>

        <RouterView class="flex-1" />
    </div>
</template>
