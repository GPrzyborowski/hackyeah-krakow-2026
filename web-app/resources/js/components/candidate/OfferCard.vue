<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Clock,
    FileText,
    HeartHandshake,
    MapPin,
    Star,
} from '@lucide/vue';
import { computed } from 'vue';
import Chip from '@/components/candidate/Chip.vue';
import {
    formatRating,
    formatSalary,
    formatShortDate,
} from '@/components/candidate/format';
import InterestButton from '@/components/candidate/InterestButton.vue';
import MatchPill from '@/components/candidate/MatchPill.vue';
import SaveOfferButton from '@/components/candidate/SaveOfferButton.vue';
import type { CandidateOffer } from '@/components/candidate/types';
import VerifiedCompanyBadge from '@/components/brand/VerifiedCompanyBadge.vue';
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';
import { show } from '@/routes/candidate/offers';

const { offer, href } = defineProps<{
    offer: CandidateOffer;
    /** Detail link target; defaults to the candidate offer page with the match breakdown. */
    href?: NonNullable<InertiaLinkProps['href']>;
}>();

const detailHref = computed(() => href ?? show(offer.id));

const perks = computed(() =>
    [
        offer.flexible_hours ? 'Elastyczne godziny' : null,
        offer.fixed_meeting_hours ? 'Spotkania przed 15:00' : null,
        offer.childcare_subsidy ? 'Dofinansowanie żłobka' : null,
        offer.nursery_distance_km !== null
            ? `Przedszkole ${offer.nursery_distance_km} km`
            : null,
    ].filter((perk): perk is string => perk !== null),
);
</script>

<template>
    <article class="rounded-3xl bg-white p-5 shadow-sm md:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="min-w-0 flex-1">
                <p
                    class="mb-1 text-xs font-semibold tracking-wide text-brand-green/70 uppercase"
                    data-test="offer-card-category"
                >
                    {{ offer.category_label }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="detailHref"
                        class="text-xl font-bold text-brand-green hover:underline"
                    >
                        {{ offer.title }}
                    </Link>
                    <Chip v-if="offer.is_parent_friendly" tone="peach">
                        <HeartHandshake class="size-3.5" /> Przyjazna rodzicom
                    </Chip>
                </div>
                <p class="mt-1 text-sm text-brand-green/80">
                    {{ offer.company.name }}
                    <VerifiedCompanyBadge
                        v-if="offer.company.verified"
                        compact
                        class="align-middle"
                    />
                    · {{ offer.city ?? 'Polska' }}
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
            <Chip tone="mint">
                <Clock class="size-3.5" aria-hidden="true" />
                {{ offer.employment_fraction_label }}
            </Chip>
            <Chip
                v-for="label in offer.contract_type_labels"
                :key="label"
                tone="mint"
                data-test="offer-card-contract-type"
            >
                <FileText class="size-3.5" aria-hidden="true" />
                {{ label }}
            </Chip>
            <Chip tone="mint">
                <MapPin class="size-3.5" aria-hidden="true" />
                {{ offer.work_mode_label }}
            </Chip>
            <Chip tone="mint">
                <CalendarDays class="size-3.5" aria-hidden="true" />
                Start od {{ formatShortDate(offer.start_date) }}
            </Chip>
        </div>
        <ul v-if="perks.length" class="mt-2 flex flex-wrap gap-2">
            <li v-for="perk in perks" :key="perk">
                <Chip tone="outline">
                    <Check class="size-3.5" aria-hidden="true" />
                    {{ perk }}
                </Chip>
            </li>
        </ul>

        <div
            class="mt-4 flex flex-col gap-4 border-t border-brand-cream pt-4 md:flex-row md:items-center"
        >
            <p class="line-clamp-2 min-w-0 flex-1 text-sm text-brand-green/80">
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
                            class="text-brand-green/80"
                        >
                            {{ offer.company.first_review.author_label }}
                        </span>
                    </template>
                </template>
                <template v-else>
                    Firma nie ma jeszcze opinii rodziców.
                </template>
            </p>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <Link
                    :href="detailHref"
                    class="rounded-full border border-brand-green px-4 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                >
                    Szczegóły
                </Link>
                <SaveOfferButton
                    :offer-id="offer.id"
                    :is-saved="offer.is_saved"
                />
                <InterestButton
                    :offer-id="offer.id"
                    :is-interested="offer.is_interested"
                />
            </div>
        </div>
    </article>
</template>
