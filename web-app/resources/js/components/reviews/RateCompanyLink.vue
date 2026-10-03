<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { create } from '@/routes/reviews';

/**
 * Small "Oceń firmę" pill other pages can include for a company the candidate worked with.
 */
defineProps<{ companyId: number }>();
</script>

<template>
    <Link
        :href="create(companyId)"
        class="inline-flex items-center gap-1.5 rounded-full border border-brand-green px-4 py-1.5 text-sm font-semibold text-brand-green transition hover:bg-brand-cream"
        data-test="rate-company-link"
    >
        <Star class="size-4" /> Oceń firmę
    </Link>
</template>
