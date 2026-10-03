<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarCheck,
    CalendarX,
    Check,
    HeartHandshake,
    Star,
    X,
} from '@lucide/vue';
import VerifiedCompanyBadge from '@/components/brand/VerifiedCompanyBadge.vue';
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
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';
import OfferJobSharePanel from '@/components/job-sharing/OfferJobSharePanel.vue';
import type { OfferJobSharing } from '@/components/job-sharing/types';
import { index } from '@/routes/candidate/offers';

type Review = {
    id: number;
    quote: string | null;
    author_label: string | null;
    rating: number;
    rating_return: number;
    rating_flexibility: number;
    rating_no_pregnancy_questions: number;
};

const {
    offer,
    availableFrom,
    reviews,
    jobSharing = null,
} = defineProps<{
    offer: CandidateOffer & {
        description: string | null;
        company_description: string | null;
    };
    availableFrom: string | null;
    reviews: Review[];
    jobSharing?: OfferJobSharing | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Oferty', href: index() }],
    },
});

const ratingCategories: { key: keyof Review; label: string }[] = [
    { key: 'rating_return', label: 'Powrót po urlopie' },
    { key: 'rating_flexibility', label: 'Elastyczność' },
    { key: 'rating_no_pregnancy_questions', label: 'Bez pytań o ciążę' },
];
</script>

<template>
    <Head :title="offer.title" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <Link
            :href="index()"
            class="inline-flex items-center gap-1 self-start text-sm font-semibold text-brand-green hover:underline"
        >
            <ArrowLeft class="size-4" /> Wszystkie oferty
        </Link>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <section class="rounded-3xl bg-white p-6 shadow-sm md:p-8">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h1
                                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-4xl"
                            >
                                {{ offer.title }}
                            </h1>
                            <p class="mt-1 text-brand-green/80">
                                {{ offer.company.name }}
                                <VerifiedCompanyBadge
                                    v-if="offer.company.verified"
                                    class="align-middle"
                                />
                                <template v-if="offer.city">
                                    · {{ offer.city }}</template
                                >
                                · {{ offer.work_mode_label.toLowerCase() }}
                            </p>
                        </div>
                        <MatchPill
                            :score="offer.match.score"
                            prefix="Dopasowanie "
                        />
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Chip v-if="offer.is_parent_friendly" tone="peach">
                            <HeartHandshake class="size-3.5" /> Przyjazna
                            rodzicom
                        </Chip>
                        <JobShareChip
                            v-if="offer.job_share?.is_job_share"
                            :hours-per-person="offer.job_share.hours_per_person"
                        />
                        <Chip>{{ offer.employment_fraction_label }}</Chip>
                        <Chip>{{ offer.work_mode_label }}</Chip>
                        <Chip v-if="offer.nursery_distance_km !== null"
                            >Przedszkole
                            {{ offer.nursery_distance_km }} km</Chip
                        >
                        <Chip
                            >Start od
                            {{ formatShortDate(offer.start_date, true) }}</Chip
                        >
                    </div>

                    <p
                        v-if="formatSalary(offer.salary_min, offer.salary_max)"
                        class="mt-5 text-2xl font-bold text-brand-green"
                    >
                        {{ formatSalary(offer.salary_min, offer.salary_max) }}
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-2">
                        <InterestButton
                            :offer-id="offer.id"
                            :is-interested="offer.is_interested"
                        />
                        <SaveOfferButton
                            :offer-id="offer.id"
                            :is-saved="offer.is_saved"
                        />
                    </div>
                </section>

                <OfferJobSharePanel
                    v-if="jobSharing"
                    :offer-id="offer.id"
                    :job-sharing="jobSharing"
                />

                <section class="rounded-3xl bg-white p-6 shadow-sm md:p-8">
                    <h2 class="text-xl font-bold text-brand-green">
                        O stanowisku
                    </h2>
                    <p
                        class="mt-3 text-sm leading-relaxed whitespace-pre-line text-brand-green/80"
                    >
                        {{ offer.description || 'Brak opisu.' }}
                    </p>

                    <h3 class="mt-6 font-bold text-brand-green">Warunki</h3>
                    <ul
                        class="mt-3 grid gap-2 text-sm text-brand-green sm:grid-cols-2"
                    >
                        <li
                            v-for="condition in [
                                {
                                    label: 'Elastyczne godziny pracy',
                                    on: offer.flexible_hours,
                                },
                                {
                                    label: 'Stałe godziny spotkań',
                                    on: offer.fixed_meeting_hours,
                                },
                                {
                                    label: 'Dopłata do żłobka / przedszkola',
                                    on: offer.childcare_subsidy,
                                },
                                {
                                    label:
                                        offer.nursery_distance_km !== null
                                            ? `Żłobek lub przedszkole ${offer.nursery_distance_km} km od miejsca pracy`
                                            : 'Żłobek lub przedszkole w pobliżu',
                                    on: offer.nursery_distance_km !== null,
                                },
                                {
                                    label: 'Widełki płacowe w ogłoszeniu',
                                    on: offer.salary_min !== null,
                                },
                            ]"
                            :key="condition.label"
                            class="flex items-center gap-2"
                        >
                            <Check
                                v-if="condition.on"
                                class="size-4 text-brand-green-soft"
                                aria-hidden="true"
                            />
                            <X
                                v-else
                                class="size-4 text-brand-green/60"
                                aria-hidden="true"
                            />
                            <span
                                :class="{
                                    'text-brand-green/80': !condition.on,
                                }"
                            >
                                <span class="sr-only">{{
                                    condition.on
                                        ? 'Spełnione:'
                                        : 'Niespełnione:'
                                }}</span>
                                {{ condition.label }}
                            </span>
                        </li>
                    </ul>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-sm md:p-8">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="text-xl font-bold text-brand-green">
                            Opinie rodziców o firmie {{ offer.company.name }}
                        </h2>
                        <span
                            v-if="offer.company.average_rating !== null"
                            class="inline-flex items-center gap-1 font-semibold text-brand-green"
                        >
                            <Star
                                class="size-4 fill-brand-yellow text-brand-yellow"
                            />
                            {{ formatRating(offer.company.average_rating) }} z 5
                        </span>
                    </div>
                    <p
                        v-if="offer.company_description"
                        class="mt-2 text-sm text-brand-green/80"
                    >
                        {{ offer.company_description }}
                    </p>

                    <p
                        v-if="reviews.length === 0"
                        class="mt-4 text-sm text-brand-green/80"
                    >
                        Firma nie ma jeszcze opinii rodziców.
                    </p>
                    <ul v-else class="mt-4 flex flex-col gap-3">
                        <li
                            v-for="review in reviews"
                            :key="review.id"
                            class="rounded-2xl bg-brand-cream p-4"
                        >
                            <p v-if="review.quote" class="text-brand-green">
                                {{ review.quote }}
                            </p>
                            <p class="mt-2 text-xs text-brand-green/80">
                                {{ review.author_label || 'Anonimowo' }} ·
                                {{ formatRating(review.rating) }} z 5
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Chip
                                    v-for="category in ratingCategories"
                                    :key="category.key"
                                    tone="outline"
                                >
                                    {{ category.label }}:
                                    {{ review[category.key] }}/5
                                </Chip>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="flex flex-col gap-4">
                <section class="rounded-3xl bg-brand-green p-6 text-white">
                    <h2 class="text-lg font-bold">Dlaczego pasuje</h2>
                    <p class="mt-1 text-sm text-white/70">
                        Liczymy tylko umiejętności, które zatwierdziłaś.
                    </p>

                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <p class="font-semibold">Wymagane</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Chip
                                    v-for="skill in offer.match
                                        .matched_required"
                                    :key="skill"
                                    tone="yellow"
                                >
                                    <Check class="size-3" /> {{ skill }}
                                </Chip>
                                <span
                                    v-for="skill in offer.match
                                        .missing_required"
                                    :key="skill"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/30 px-3 py-1 text-xs text-white/70"
                                >
                                    <X class="size-3" /> {{ skill }}
                                </span>
                                <span
                                    v-if="
                                        !offer.match.matched_required.length &&
                                        !offer.match.missing_required.length
                                    "
                                    class="text-white/80"
                                    >Brak wymagań</span
                                >
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold">Mile widziane</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Chip
                                    v-for="skill in offer.match
                                        .matched_nice_to_have"
                                    :key="skill"
                                    tone="yellow"
                                >
                                    <Check class="size-3" /> {{ skill }}
                                </Chip>
                                <span
                                    v-for="skill in offer.match
                                        .missing_nice_to_have"
                                    :key="skill"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/30 px-3 py-1 text-xs text-white/70"
                                >
                                    <X class="size-3" /> {{ skill }}
                                </span>
                                <span
                                    v-if="
                                        !offer.match.matched_nice_to_have
                                            .length &&
                                        !offer.match.missing_nice_to_have.length
                                    "
                                    class="text-white/80"
                                    >—</span
                                >
                            </div>
                        </div>
                        <div
                            class="flex items-start gap-2 rounded-2xl bg-white/10 p-3"
                        >
                            <CalendarCheck
                                v-if="offer.match.start_date_compatible"
                                class="mt-0.5 size-4 shrink-0 text-brand-yellow"
                            />
                            <CalendarX
                                v-else
                                class="mt-0.5 size-4 shrink-0 text-brand-peach"
                            />
                            <p>
                                <template
                                    v-if="offer.match.start_date_compatible"
                                >
                                    Zdążysz: jesteś gotowa od
                                    {{ formatShortDate(availableFrom, true) }},
                                    start
                                    {{
                                        formatShortDate(offer.start_date, true)
                                    }}.
                                </template>
                                <template v-else-if="availableFrom">
                                    Twoja data gotowości ({{
                                        formatShortDate(availableFrom, true)
                                    }}) jest ponad 30 dni po starcie.
                                </template>
                                <template v-else>
                                    Uzupełnij datę gotowości w profilu.
                                </template>
                            </p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
