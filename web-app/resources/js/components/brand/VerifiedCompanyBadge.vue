<script setup lang="ts">
import { BadgeCheck } from '@lucide/vue';

const { compact = false } = defineProps<{
    compact?: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full bg-brand-mint-soft font-semibold whitespace-nowrap text-brand-green"
        :class="compact ? 'px-2 py-0.5 text-[11px]' : 'px-3 py-1 text-xs'"
        title="MomJobs sprawdził NIP i dane tej firmy"
        data-test="verified-company-badge"
    >
        <BadgeCheck
            class="text-brand-green"
            :class="compact ? 'size-3' : 'size-3.5'"
            aria-hidden="true"
        />
        Zweryfikowana firma
    </span>
</template>
