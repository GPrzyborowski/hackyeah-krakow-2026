<script setup lang="ts">
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    hasSalaryRange: boolean;
    hasFlexibleHours: boolean;
    hasApprovedReview: boolean;
}>();

const conditions = computed(() => [
    { label: 'Podane widełki wynagrodzenia', met: props.hasSalaryRange },
    { label: 'Opisane elastyczne godziny', met: props.hasFlexibleHours },
    {
        label: 'Co najmniej jedna opinia rodzica o firmie',
        met: props.hasApprovedReview,
    },
]);
</script>

<template>
    <section class="rounded-3xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-brand-green">
            Odznaka „przyjazna rodzicom”
        </h2>
        <p class="mt-1 text-sm text-brand-green/70">
            Dostaniesz ją po spełnieniu trzech warunków.
        </p>
        <ul class="mt-4 space-y-2 text-sm">
            <li
                v-for="condition in conditions"
                :key="condition.label"
                class="flex items-center gap-2 text-brand-green"
            >
                <Check v-if="condition.met" class="size-4 text-brand-green" />
                <Circle v-else class="size-3.5 text-amber-500" />
                <span :class="{ 'text-brand-green/70': !condition.met }">{{
                    condition.label
                }}</span>
            </li>
        </ul>
    </section>
</template>
