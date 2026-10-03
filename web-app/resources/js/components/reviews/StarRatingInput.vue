<script setup lang="ts">
import { Star } from '@lucide/vue';
import { ref, useId } from 'vue';
import InputError from '@/components/InputError.vue';

const props = defineProps<{
    name: string;
    label: string;
    error?: string;
}>();

const value = defineModel<number | null>({ default: null });
const hovered = ref<number | null>(null);

const starLabels = ['bardzo źle', 'słabo', 'w porządku', 'dobrze', 'świetnie'];
const starButtons = ref<HTMLButtonElement[]>([]);
const errorId = `${useId()}-error`;

function isTabStop(star: number): boolean {
    return value.value ? value.value === star : star === 1;
}

function selectWithKeyboard(event: KeyboardEvent, star: number): void {
    const keyStep: Record<string, number> = {
        ArrowRight: 1,
        ArrowUp: 1,
        ArrowLeft: -1,
        ArrowDown: -1,
    };
    let next: number | null = null;

    if (event.key in keyStep) {
        next = ((star - 1 + keyStep[event.key] + 5) % 5) + 1;
    } else if (event.key === 'Home') {
        next = 1;
    } else if (event.key === 'End') {
        next = 5;
    }

    if (next === null) {
        return;
    }

    event.preventDefault();
    value.value = next;
    starButtons.value[next - 1]?.focus();
}
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
            :aria-describedby="props.error ? errorId : undefined"
            @mouseleave="hovered = null"
        >
            <button
                v-for="star in 5"
                :key="star"
                type="button"
                role="radio"
                :aria-checked="value === star"
                :aria-label="`${star} z 5 – ${starLabels[star - 1]}`"
                :tabindex="isTabStop(star) ? 0 : -1"
                class="rounded-full p-1 transition hover:scale-110 focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none motion-reduce:transition-none motion-reduce:hover:scale-100"
                :data-test="`${props.name}-star-${star}`"
                ref="starButtons"
                @mouseenter="hovered = star"
                @click="value = star"
                @keydown="selectWithKeyboard($event, star)"
            >
                <Star
                    aria-hidden="true"
                    class="size-7"
                    :class="
                        star <= (hovered ?? value ?? 0)
                            ? 'fill-brand-yellow text-brand-yellow'
                            : 'text-brand-green/60'
                    "
                />
            </button>
            <span v-if="value" class="ml-2 text-xs text-brand-green/80">
                {{ starLabels[value - 1] }}
            </span>
        </div>
        <InputError :id="errorId" :message="props.error" />
    </fieldset>
</template>
