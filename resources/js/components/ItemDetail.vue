<script setup lang="ts">
import { computed } from 'vue';
import type { DecryptedItem } from '@/types/item';
import { FIELDS_BY_TYPE, SECRET_FIELDS, TYPE_LABEL } from '@/types/item';
import { expiryStatus, expiryMessage } from '@/lib/expiry';
import { useClipboard } from '@/composables/useClipboard';
import SecretField from '@/components/SecretField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import Notice from '@/components/ui/Notice.vue';

const props = defineProps<{ item: DecryptedItem }>();

const emit = defineEmits<{ remove: [] }>();

const { copy, copied, secondsLeft, error } = useClipboard();

const fields = computed(() =>
    FIELDS_BY_TYPE[props.item.type].map((name) => ({
        name,
        label: name.replace(/_/g, ' '),
        value: props.item.fields[name] ?? '',
        secret: SECRET_FIELDS[props.item.type].includes(name),
        block: name === 'content' || name === 'notes',
    })),
);

const expiry = computed(() => expiryStatus(props.item.fields.expires_at));
const isProduction = computed(() => (props.item.fields.environment ?? '').trim().toLowerCase() === 'prod');
</script>

<template>
    <article class="flex min-w-0 flex-1 flex-col gap-5 p-6">
        <header class="flex flex-wrap items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span v-if="item.favorite" class="text-brass" aria-label="Favourite">★</span>
                    <h1 class="truncate text-xl font-semibold tracking-tight text-paper">{{ item.name }}</h1>
                </div>

                <p class="mt-1 font-mono text-xs text-haze">
                    {{ TYPE_LABEL[item.type] }}<span v-if="item.folder"> · {{ item.folder }}</span>
                </p>
            </div>

            <div class="flex shrink-0 gap-2">
                <AppButton variant="danger" @click="emit('remove')">Move to trash</AppButton>
            </div>
        </header>

        <Notice v-if="isProduction" tone="alarm">
            This credential is marked <span class="font-mono">prod</span>. Anything you do with it happens to real
            users.
        </Notice>

        <Notice v-if="expiry && (expiry.expired || expiry.soon)" :tone="expiry.expired ? 'alarm' : 'brass'">
            {{ expiryMessage(expiry) }}.
        </Notice>

        <Notice v-if="error" tone="alarm">{{ error }}</Notice>

        <section class="flex flex-col border-t border-line">
            <SecretField
                v-for="field in fields"
                :key="field.name"
                :label="field.label"
                :value="field.value"
                :secret="field.secret"
                :block="field.block"
                :copied-label="copied"
                :seconds-left="secondsLeft"
                @copy="copy"
            />
        </section>

        <footer class="font-mono text-xs text-haze">
            Updated {{ new Date(item.updated_at).toLocaleString() }} · revision {{ item.version }}
        </footer>
    </article>
</template>
