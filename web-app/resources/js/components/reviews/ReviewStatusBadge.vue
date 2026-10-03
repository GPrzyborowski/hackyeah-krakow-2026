<script setup lang="ts">
import { reviewStatusLabels } from '@/components/reviews/types';
import type { ReviewStatus } from '@/components/reviews/types';

defineProps<{ status: ReviewStatus }>();
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
        :class="{
            'bg-brand-yellow text-brand-green': status === 'pending',
            'bg-brand-green text-white': status === 'approved',
            'bg-brand-peach text-brand-green': status === 'rejected',
        }"
        :data-test="`review-status-${status}`"
    >
        {{ reviewStatusLabels[status] }}
    </span>
</template>
