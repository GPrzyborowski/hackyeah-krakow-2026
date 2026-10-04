<script setup lang="ts">
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';
import { formatShortDate } from '@/components/employer/format';
import type { MatchDetails } from '@/components/employer/types';

const props = defineProps<{
    match: MatchDetails;
    offerStartDate: string;
}>();

const rows = computed(() => [
    ...props.match.matched_required.map((name) => ({
        name,
        matched: true,
        required: true,
    })),
    ...props.match.matched_nice_to_have.map((name) => ({
        name,
        matched: true,
        required: false,
    })),
    ...props.match.missing_required.map((name) => ({
        name,
        matched: false,
        required: true,
    })),
    ...props.match.missing_nice_to_have.map((name) => ({
        name,
        matched: false,
        required: false,
    })),
]);
</script>

<template>
    <section class="rounded-3xl bg-brand-green p-6 text-white shadow-sm">
        <h2 class="text-lg font-semibold">Dopasowanie do ogłoszenia</h2>
        <ul class="mt-4 space-y-2.5 text-sm">
            <li
                v-for="row in rows"
                :key="row.name"
                class="flex items-center gap-2"
            >
                <Check
                    v-if="row.matched"
                    class="size-4 shrink-0 text-brand-yellow"
                />
                <Circle v-else class="size-3.5 shrink-0 text-white/70" />
                <span :class="{ 'text-white/75': !row.matched }">
                    {{ row.name }}<span v-if="!row.matched">, brak w CV</span>
                    <span v-if="!row.required" class="text-white/80">
                        (mile widziane)</span
                    >
                </span>
            </li>
        </ul>
        <div class="mt-4 border-t border-white/15 pt-4 text-sm text-white/85">
            <template v-if="match.start_date_compatible">
                Termin startu zgodny z ogłoszeniem:
                <strong class="text-white">{{
                    formatShortDate(offerStartDate)
                }}</strong>
            </template>
            <template v-else>
                Termin startu może się nie pokrywać z ogłoszeniem ({{
                    formatShortDate(offerStartDate)
                }}).
            </template>
        </div>
    </section>
</template>
