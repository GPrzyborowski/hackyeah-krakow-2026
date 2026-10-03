<script setup lang="ts">
import { UsersRound } from '@lucide/vue';
import { jobShareLabel } from '@/components/job-sharing/format';

const { hoursPerPerson = null } = defineProps<{
    hoursPerPerson?: number | null;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full bg-brand-mint-soft px-3 py-1 text-xs font-medium text-brand-green"
        data-test="job-share-chip"
    >
        <UsersRound class="size-3.5" /> Job sharing ·
        {{ jobShareLabel(hoursPerPerson) }}
    </span>
</template>
