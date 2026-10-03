<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { show } from '@/routes/candidate/onboarding';

const props = defineProps<{
    step: number;
    completedStep: number;
    isPublished: boolean;
}>();

const steps = [
    { number: 1, label: 'Konto' },
    { number: 2, label: 'CV i umiejętności' },
    { number: 3, label: 'Preferencje pracy' },
    { number: 4, label: 'Prywatność' },
];

const furthestReachable = computed(() =>
    props.isPublished ? 4 : Math.min(props.completedStep + 1, 4),
);

function isDone(number: number): boolean {
    return number === 1 || number <= props.completedStep;
}

function isReachable(number: number): boolean {
    return number >= 2 && number <= furthestReachable.value;
}
</script>

<template>
    <div>
        <!-- Mobile: "Krok 2 z 4" + progress bars -->
        <div class="lg:hidden">
            <p class="text-center text-sm font-semibold text-brand-green">
                Krok {{ step }} z 4
            </p>
            <div class="mt-3 grid grid-cols-4 gap-1.5">
                <span
                    v-for="item in steps"
                    :key="item.number"
                    class="h-1.5 rounded-full"
                    :class="
                        item.number <= step
                            ? 'bg-brand-green'
                            : 'bg-brand-mint-soft'
                    "
                />
            </div>
        </div>

        <!-- Desktop: vertical stepper -->
        <div class="hidden lg:block">
            <h1
                class="text-4xl leading-tight font-extrabold tracking-tight text-brand-green"
            >
                {{ isPublished ? 'Twój profil' : 'Stwórz profil w 4 krokach' }}
            </h1>
            <ol class="mt-8 space-y-4">
                <li v-for="item in steps" :key="item.number">
                    <component
                        :is="isReachable(item.number) ? Link : 'span'"
                        v-bind="
                            isReachable(item.number)
                                ? {
                                      href: show({
                                          query: { step: item.number },
                                      }),
                                  }
                                : {}
                        "
                        class="flex items-center gap-3 text-sm text-brand-green"
                        :class="{
                            'font-semibold': item.number === step,
                            'opacity-60':
                                !isReachable(item.number) &&
                                !isDone(item.number),
                        }"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-full text-xs font-bold"
                            :class="
                                item.number === step
                                    ? 'bg-brand-peach text-brand-green'
                                    : isDone(item.number)
                                      ? 'bg-brand-green text-white'
                                      : 'border border-brand-green/30 bg-white text-brand-green'
                            "
                        >
                            <Check
                                v-if="
                                    isDone(item.number) && item.number !== step
                                "
                                class="size-4"
                            />
                            <template v-else>{{ item.number }}</template>
                        </span>
                        {{ item.label }}
                    </component>
                </li>
            </ol>
            <p class="mt-6 text-xs leading-relaxed text-brand-green/60">
                Możesz przerwać w dowolnym momencie. Profil zapisuje się sam po
                każdym kroku.
            </p>
        </div>
    </div>
</template>
