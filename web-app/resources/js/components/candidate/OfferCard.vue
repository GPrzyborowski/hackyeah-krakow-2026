<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { HeartHandshake, Star } from '@lucide/vue';
import Chip from '@/components/candidate/Chip.vue';
import {
    formatRating,
    formatSalary,
    formatShortDate,
} from '@/components/candidate/format';
import InterestButton from '@/components/candidate/InterestButton.vue';
import MatchPill from '@/components/candidate/MatchPill.vue';
import type { CandidateOffer } from '@/components/candidate/types';
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';
import { show } from '@/routes/candidate/offers';

const { offer } = defineProps<{ offer: CandidateOffer }>();

const location = [offer.city, offer.work_mode === 'remote' ? 'zdalnie' : null]
    .filter(Boolean)
    .join(' lub ');
</script>

<template>
    <article class="rounded-3xl bg-white p-5 shadow-sm md:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="show(offer.id)"
                        class="text-xl font-bold text-brand-green hover:underline"
                    >
                        {{ offer.title }}
                    </Link>
                    <Chip v-if="offer.is_parent_friendly" tone="peach">
                        <HeartHandshake class="size-3.5" /> Przyjazna rodzicom
                    </Chip>
                </div>
                <p class="mt-1 text-sm text-brand-green/70">
                    {{ offer.company.name }} ·
                    {{ location || offer.work_mode_label.toLowerCase() }}
                </p>
            </div>
            <div
                class="flex flex-row items-center gap-3 sm:flex-col sm:items-end"
            >
                <MatchPill :score="offer.match.score" prefix="Dopasowanie " />
                <p
                    v-if="formatSalary(offer.salary_min, offer.salary_max)"
                    class="text-sm font-bold text-brand-green"
                >
                    {{ formatSalary(offer.salary_min, offer.salary_max) }}
                </p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <JobShareChip
                v-if="offer.job_share?.is_job_share"
                :hours-per-person="offer.job_share.hours_per_person"
            />
            <Chip>{{ offer.employment_fraction_label }}</Chip>
            <Chip>{{ offer.work_mode_label }}</Chip>
            <Chip v-if="offer.flexible_hours">Elastyczne godziny</Chip>
            <Chip v-if="offer.childcare_subsidy">Dopłata do żłobka</Chip>
            <Chip>Start od {{ formatShortDate(offer.start_date) }}</Chip>
        </div>

        <div
            class="mt-4 flex flex-col gap-4 border-t border-brand-cream pt-4 md:flex-row md:items-center"
        >
            <p class="flex-1 text-sm text-brand-green/80">
                <template v-if="offer.company.average_rating !== null">
                    <span class="inline-flex items-center gap-1 font-semibold">
                        <Star
                            class="size-3.5 fill-brand-yellow text-brand-yellow"
                        />
                        {{ formatRating(offer.company.average_rating) }} z 5
                    </span>
                    <template v-if="offer.company.first_review">
                        · {{ offer.company.first_review.quote }}
                        <span
                            v-if="offer.company.first_review.author_label"
                            class="text-brand-green/60"
                        >
                            {{ offer.company.first_review.author_label }}
                        </span>
                    </template>
                </template>
                <template v-else>
                    Firma nie ma jeszcze opinii rodziców.
                </template>
            </p>
            <div class="flex shrink-0 items-center gap-2">
                <Link
                    :href="show(offer.id)"
                    class="rounded-full border border-brand-green px-4 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                >
                    Szczegóły
                </Link>
                <InterestButton
                    :offer-id="offer.id"
                    :is-interested="offer.is_interested"
                />
            </div>
        </div>
    </article>
</template>
