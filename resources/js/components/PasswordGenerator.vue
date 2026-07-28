<script setup lang="ts">
import { ref, watch } from 'vue';
import { generatePassword, MIN_LENGTH } from '@/lib/generator';
import AppButton from '@/components/ui/AppButton.vue';

const emit = defineEmits<{ use: [password: string] }>();

const length = ref(24);
const symbols = ref(true);
const digits = ref(true);
const excludeAmbiguous = ref(false);
const candidate = ref('');

function regenerate(): void {
    candidate.value = generatePassword({
        length: length.value,
        symbols: symbols.value,
        digits: digits.value,
        excludeAmbiguous: excludeAmbiguous.value,
    });
}

watch([length, symbols, digits, excludeAmbiguous], regenerate, { immediate: true });
</script>

<template>
    <section class="flex flex-col gap-4 border border-line bg-panel p-4">
        <div class="flex items-center gap-3">
            <p class="min-w-0 flex-1 font-mono text-sm break-all text-brass">{{ candidate }}</p>

            <AppButton @click="regenerate">New one</AppButton>
            <AppButton variant="primary" @click="emit('use', candidate)">Use this</AppButton>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-haze">
            <label class="flex items-center gap-2">
                <span class="font-mono text-xs tabular-nums">{{ length }}</span>
                <input
                    v-model.number="length"
                    type="range"
                    :min="MIN_LENGTH"
                    max="64"
                    class="accent-brass"
                    aria-label="Length"
                />
            </label>

            <label class="flex cursor-pointer items-center gap-2">
                <input v-model="digits" type="checkbox" class="accent-brass" />
                Digits
            </label>

            <label class="flex cursor-pointer items-center gap-2">
                <input v-model="symbols" type="checkbox" class="accent-brass" />
                Symbols
            </label>

            <label class="flex cursor-pointer items-center gap-2">
                <input v-model="excludeAmbiguous" type="checkbox" class="accent-brass" />
                Skip look-alike characters
            </label>
        </div>
    </section>
</template>
