<script setup lang="ts">
import { ref, reactive, computed, useId } from 'vue';
import type { DecryptedItem, ItemType, PlainItem } from '@/types/item';
import { FIELDS_BY_TYPE, SECRET_FIELDS, TYPE_LABEL } from '@/types/item';
import AppButton from '@/components/ui/AppButton.vue';
import TextField from '@/components/ui/TextField.vue';
import Notice from '@/components/ui/Notice.vue';
import PasswordGenerator from '@/components/PasswordGenerator.vue';

const props = defineProps<{ item: DecryptedItem | null; folders: string[]; busy: boolean; error: string }>();

const emit = defineEmits<{ save: [item: PlainItem]; cancel: [] }>();

const draft = reactive<PlainItem>({
    type: props.item?.type ?? 'login',
    name: props.item?.name ?? '',
    folder: props.item?.folder ?? '',
    favorite: props.item?.favorite ?? false,
    fields: { ...(props.item?.fields ?? {}) },
});

const showGenerator = ref(false);
const nameError = ref('');
const foldersId = useId();

const fields = computed(() =>
    FIELDS_BY_TYPE[draft.type].map((name) => ({
        name,
        label: name.replace(/_/g, ' '),
        secret: SECRET_FIELDS[draft.type].includes(name),
        multiline: name === 'content' || name === 'notes',
    })),
);

function fieldValue(name: string): string {
    return draft.fields[name] ?? '';
}

function setField(name: string, value: string): void {
    draft.fields[name] = value;
}

function changeType(type: ItemType): void {
    draft.type = type;
    // Fields that the new type does not have would otherwise be carried along
    // invisibly inside the ciphertext.
    Object.keys(draft.fields).forEach((name) => {
        if (!FIELDS_BY_TYPE[type].includes(name)) delete draft.fields[name];
    });
}

function useGenerated(password: string): void {
    setField('password', password);
    showGenerator.value = false;
}

function save(): void {
    if (draft.name.trim() === '') {
        nameError.value = 'Give this item a name so you can find it again.';

        return;
    }

    nameError.value = '';
    emit('save', { ...draft, name: draft.name.trim(), folder: draft.folder.trim(), fields: { ...draft.fields } });
}
</script>

<template>
    <!--
        Deliberately not a <form>: a real one makes browsers offer to save the
        credential in their own password manager, which would put a plaintext
        copy exactly where this vault exists to avoid.
    -->
    <div class="flex min-w-0 flex-1 flex-col gap-5 p-6">
        <header class="flex items-center gap-3">
            <h1 class="flex-1 text-xl font-semibold tracking-tight">
                {{ item === null ? 'New item' : 'Edit item' }}
            </h1>

            <AppButton @click="emit('cancel')">Cancel</AppButton>
            <AppButton variant="primary" :busy="busy" @click="save">Save</AppButton>
        </header>

        <Notice v-if="error" tone="alarm">{{ error }}</Notice>
        <Notice v-else-if="nameError" tone="alarm">{{ nameError }}</Notice>

        <div v-if="item === null" class="flex flex-wrap gap-2">
            <button
                v-for="(label, value) in TYPE_LABEL"
                :key="value"
                type="button"
                class="rounded-sm border px-3 py-1.5 text-sm"
                :class="draft.type === value ? 'border-brass text-brass' : 'border-line text-haze hover:text-paper'"
                @click="changeType(value as ItemType)"
            >
                {{ label }}
            </button>
        </div>

        <TextField v-model="draft.name" label="Name" autofocus placeholder="What you would call it" />

        <div class="flex flex-col gap-1.5">
            <TextField v-model="draft.folder" label="Folder" :list="foldersId" placeholder="Optional" />
            <datalist :id="foldersId">
                <option v-for="name in folders" :key="name" :value="name" />
            </datalist>
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-haze">
            <input v-model="draft.favorite" type="checkbox" class="accent-brass" />
            Keep this at the top of the list
        </label>

        <section class="flex flex-col gap-4 border-t border-line pt-5">
            <template v-for="field in fields" :key="field.name">
                <div v-if="field.multiline" class="flex flex-col gap-1.5">
                    <label :for="`${foldersId}-${field.name}`" class="text-xs font-medium tracking-wide text-haze uppercase">
                        {{ field.label }}
                    </label>
                    <textarea
                        :id="`${foldersId}-${field.name}`"
                        :value="fieldValue(field.name)"
                        rows="6"
                        autocomplete="off"
                        spellcheck="false"
                        class="rounded-sm border border-line bg-ink px-3 py-2 font-mono text-sm text-paper focus:border-brass"
                        @input="setField(field.name, ($event.target as HTMLTextAreaElement).value)"
                    ></textarea>
                </div>

                <div v-else class="flex items-end gap-2">
                    <div class="flex-1">
                        <TextField
                            :model-value="fieldValue(field.name)"
                            :label="field.label"
                            :mono="field.secret"
                            :type="field.name === 'expires_at' ? 'date' : 'text'"
                            autocomplete="off"
                            @update:model-value="setField(field.name, $event)"
                        />
                    </div>

                    <AppButton v-if="field.name === 'password'" @click="showGenerator = !showGenerator">
                        {{ showGenerator ? 'Close' : 'Generate' }}
                    </AppButton>
                </div>

                <!-- Right under the field it fills: opening it at the foot of the
                     form would put it off-screen, away from the button. -->
                <PasswordGenerator
                    v-if="field.name === 'password' && showGenerator"
                    @use="useGenerated"
                />
            </template>
        </section>
    </div>
</template>
