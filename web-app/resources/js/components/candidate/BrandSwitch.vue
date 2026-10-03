<script setup lang="ts">
import { useId } from 'vue';

const model = defineModel<boolean>({ required: true });

defineProps<{
    label: string;
    description?: string;
    disabled?: boolean;
}>();

const descriptionId = useId();

const emit = defineEmits<{ change: [value: boolean] }>();

function toggle() {
    model.value = !model.value;
    emit('change', model.value);
}
</script>

<template>
    <div class="flex items-center justify-between gap-4 py-3">
        <div class="text-sm leading-snug text-brand-green">
            <p>{{ label }}</p>
            <p
                v-if="description"
                :id="descriptionId"
                class="mt-0.5 text-xs text-brand-green/80"
            >
                {{ description }}
            </p>
        </div>
        <button
            type="button"
            role="switch"
            :aria-checked="model"
            :aria-label="label"
            :aria-describedby="description ? descriptionId : undefined"
            :disabled="disabled"
            class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border-2 transition-colors focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
            :class="
                model
                    ? 'border-brand-green bg-brand-green'
                    : 'border-brand-line bg-white'
            "
            @click="toggle"
        >
            <span
                class="inline-block size-5 rounded-full shadow transition-transform motion-reduce:transition-none"
                :class="
                    model
                        ? 'translate-x-5 bg-white'
                        : 'translate-x-0.5 bg-brand-line'
                "
            />
        </button>
    </div>
</template>
