<script setup lang="ts">
import { Star } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';

const props = defineProps<{
    name: string;
    label: string;
    error?: string;
}>();

const value = defineModel<number | null>({ default: null });
const hovered = ref<number | null>(null);

const starLabels = ['bardzo źle', 'słabo', 'w porządku', 'dobrze', 'świetnie'];
</script>

<template>
    <fieldset>
        <legend class="text-sm font-semibold text-brand-green">
            {{ props.label }}
        </legend>
        <input type="hidden" :name="props.name" :value="value ?? ''" />
        <div
            class="mt-2 flex items-center gap-1"
            role="radiogroup"
            :aria-label="props.label"
            @mouseleave="hovered = null"
        >
            <button
                v-for="star in 5"
                :key="star"
                type="button"
                role="radio"
                :aria-checked="value === star"
                :aria-label="`${star} z 5 – ${starLabels[star - 1]}`"
                class="rounded-full p-1 transition hover:scale-110 focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:outline-none"
                :data-test="`${props.name}-star-${star}`"
                @mouseenter="hovered = star"
                @click="value = star"
            >
                <Star
                    class="size-7"
                    :class="
                        star <= (hovered ?? value ?? 0)
                            ? 'fill-brand-yellow text-brand-yellow'
                            : 'text-brand-green/25'
                    "
                />
            </button>
            <span v-if="value" class="ml-2 text-xs text-brand-green/70">
                {{ starLabels[value - 1] }}
            </span>
        </div>
        <InputError :message="props.error" />
    </fieldset>
</template>
