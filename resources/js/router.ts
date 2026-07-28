import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useSessionStore } from '@/stores/session';

/**
 * What a route needs before it will render:
 * - `none`      reachable while signed out.
 * - `session`   the server session must be alive; the vault may still be locked.
 * - `unlocked`  the Vault Key must be in memory.
 */
type Requirement = 'none' | 'session' | 'unlocked';

declare module 'vue-router' {
    interface RouteMeta {
        requires: Requirement;
    }
}

const routes: RouteRecordRaw[] = [
    {
        path: '/setup',
        name: 'setup',
        component: () => import('@/pages/Setup.vue'),
        meta: { requires: 'none' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/Login.vue'),
        meta: { requires: 'none' },
    },
    {
        path: '/unlock',
        name: 'unlock',
        component: () => import('@/pages/Unlock.vue'),
        meta: { requires: 'session' },
    },
    {
        path: '/',
        component: () => import('@/components/VaultShell.vue'),
        meta: { requires: 'unlocked' },
        children: [
            { path: '', name: 'vault', component: () => import('@/pages/Vault.vue'), meta: { requires: 'unlocked' } },
            { path: 'trash', name: 'trash', component: () => import('@/pages/Trash.vue'), meta: { requires: 'unlocked' } },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('@/pages/Settings.vue'),
                meta: { requires: 'unlocked' },
            },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'vault' } },
];

export const router = createRouter({ history: createWebHistory(), routes });

router.beforeEach(async (to) => {
    const session = useSessionStore();

    if (!session.booted) await session.boot();

    const requires = to.meta.requires ?? 'unlocked';

    if (session.state === 'anonymous') {
        return requires === 'none' ? true : { name: 'login' };
    }

    if (session.state === 'locked' && requires === 'unlocked') {
        return { name: 'unlock' };
    }

    // Already inside: the sign-in screens have nothing left to offer. Setup is
    // the exception, because it is still finishing when it lands there.
    if (session.state === 'unlocked' && requires !== 'unlocked' && to.name !== 'setup') {
        return { name: 'vault' };
    }

    return true;
});
