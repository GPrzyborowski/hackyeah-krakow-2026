<script setup lang="ts">
const { score, prefix = '' } = defineProps<{
    score: number;
    prefix?: string;
}>();
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold text-brand-green"
        :class="
            score >= 80
                ? 'bg-brand-yellow'
                : score >= 60
                  ? 'bg-brand-peach'
                  : 'bg-brand-mint-soft'
        "
    >
        {{ prefix }}{{ score }}%
    </span>
</template>
