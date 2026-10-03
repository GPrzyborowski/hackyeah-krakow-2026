<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Citation } from '@/components/chat/types';

defineProps<{
    citations: Citation[];
}>();
</script>

<template>
    <div v-if="citations.length" class="flex flex-wrap gap-2">
        <template v-for="citation in citations" :key="citation.label">
            <Link
                v-if="citation.url"
                :href="citation.url"
                class="rounded-full border border-brand-green/30 px-3 py-1 text-xs font-medium text-brand-green transition hover:bg-brand-mint-soft"
            >
                {{ citation.label }}
            </Link>
            <span
                v-else
                class="rounded-full px-3 py-1 text-xs font-semibold text-brand-green"
                :class="
                    citation.type === 'legal'
                        ? 'bg-brand-yellow'
                        : 'border border-brand-green/30'
                "
            >
                {{ citation.label }}
            </span>
        </template>
    </div>
</template>
