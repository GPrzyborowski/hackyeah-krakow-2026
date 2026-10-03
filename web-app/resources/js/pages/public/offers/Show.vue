<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    HeartHandshake,
    Sparkles,
    Star,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    formatRating,
    formatSalary,
    formatShortDate,
} from '@/components/brand/format';
import RatingBar from '@/components/brand/RatingBar.vue';
import { ratingCategoryLabels } from '@/components/brand/types';
import type {
    FeaturedQuote,
    PublicOffer,
    RatingCategories,
    RatingSummary,
} from '@/components/brand/types';
import VerifiedCompanyBadge from '@/components/brand/VerifiedCompanyBadge.vue';
import Chip from '@/components/candidate/Chip.vue';
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';
import { formatHour, formatHours } from '@/components/job-sharing/format';
import { reviewCountLabel } from '@/lib/plural';
import { register } from '@/routes';
import { show as candidateOfferShow } from '@/routes/candidate/offers';
import { show as companyShow } from '@/routes/public/companies';
import { index as offersIndex } from '@/routes/public/offers';

type Review = {
    id: number;
    rating_return: number;
    rating_flexibility: number;
    rating_no_pregnancy_questions: number;
    overall: number;
    quote: string | null;
    author_label: string | null;
};

type PublicOfferDetail = Omit<PublicOffer, 'company'> & {
    description: string | null;
    required_skills: string[];
    nice_to_have_skills: string[];
    workday_starts_at: string | null;
    workday_ends_at: string | null;
    company_description: string | null;
    company: {
        id: number;
        name: string;
        city: string | null;
        verified: boolean;
        rating: RatingSummary;
        featured_quote: FeaturedQuote | null;
    };
};

const props = defineProps<{
    offer: PublicOfferDetail;
    reviews: Review[];
}>();

const page = usePage();

const isCandidate = computed(() => page.props.auth.role === 'candidate');
const isGuest = computed(() => !page.props.auth.user);

const salary = computed(() =>
    formatSalary(props.offer.salary_min, props.offer.salary_max),
);

const categories = Object.keys(ratingCategoryLabels) as Array<
    keyof RatingCategories
>;

const conditions = computed(() => [
    { label: 'Elastyczne godziny pracy', on: props.offer.flexible_hours },
    {
        label: 'Spotkania przed 15:00',
        on: props.offer.fixed_meeting_hours ?? false,
    },
    {
        label: 'Dofinansowanie żłobka lub przedszkola',
        on: props.offer.childcare_subsidy ?? false,
    },
    {
        label:
            props.offer.nursery_distance_km != null
                ? `Żłobek lub przedszkole ${props.offer.nursery_distance_km} km od miejsca pracy`
                : 'Żłobek lub przedszkole w pobliżu',
        on: props.offer.nursery_distance_km != null,
    },
    {
        label: 'Widełki płacowe w ogłoszeniu',
        on: props.offer.salary_min !== null,
    },
]);
</script>

<template>
    <Head :title="offer.title" />

    <div class="mx-auto max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
        <Link
            :href="offersIndex()"
            class="inline-flex items-center gap-1 text-sm font-medium text-brand-green/80 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wszystkie oferty
        </Link>

        <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <section class="rounded-3xl bg-white p-6 sm:p-8">
                    <h1
                        class="text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                    >
                        {{ offer.title }}
                    </h1>
                    <p class="mt-2 text-brand-green/80">
                        <Link
                            :href="companyShow(offer.company.id)"
                            class="hover:underline"
                            >{{ offer.company.name }}</Link
                        >
                        <VerifiedCompanyBadge
                            v-if="offer.company.verified"
                            class="ml-1 align-middle"
                        />
                        <template v-if="offer.city">
                            · {{ offer.city }}</template
                        >
                        · {{ offer.work_mode_label.toLowerCase() }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Chip v-if="offer.is_parent_friendly" tone="yellow">
                            <HeartHandshake class="size-3.5" /> Przyjazna
                            rodzicom
                        </Chip>
                        <JobShareChip
                            v-if="offer.job_share?.is_job_share"
                            :hours-per-person="offer.job_share.hours_per_person"
                        />
                        <Chip>{{ offer.employment_fraction_label }}</Chip>
                        <Chip>{{ offer.work_mode_label }}</Chip>
                        <Chip v-if="offer.flexible_hours"
                            >Elastyczne godziny</Chip
                        >
                        <Chip v-if="offer.fixed_meeting_hours"
                            >Spotkania przed 15:00</Chip
                        >
                        <Chip v-if="offer.childcare_subsidy"
                            >Dofinansowanie żłobka</Chip
                        >
                        <Chip v-if="offer.nursery_distance_km != null"
                            >Przedszkole
                            {{ offer.nursery_distance_km }} km</Chip
                        >
                        <Chip
                            >Start od
                            {{ formatShortDate(offer.start_date) }}</Chip
                        >
                    </div>

                    <p
                        v-if="salary"
                        class="mt-5 text-2xl font-semibold text-brand-green"
                    >
                        {{ salary }}
                    </p>
                </section>

                <section
                    v-if="offer.job_share?.is_job_share"
                    class="rounded-3xl bg-brand-mint-soft p-6 text-brand-green sm:p-8"
                    data-test="job-share-info"
                >
                    <h2 class="text-xl font-semibold">
                        Job sharing: jedno stanowisko, dwie osoby
                    </h2>
                    <p class="mt-2 text-sm">
                        <template
                            v-if="
                                offer.workday_starts_at && offer.workday_ends_at
                            "
                        >
                            Dzień pracy
                            {{ formatHour(offer.workday_starts_at) }}–{{
                                formatHour(offer.workday_ends_at)
                            }}
                            dzielicie między siebie
                        </template>
                        <template v-else>Etat dzielicie we dwie</template>
                        <template v-if="offer.job_share.hours_per_person">
                            – ok.
                            {{ formatHours(offer.job_share.hours_per_person) }}
                            dziennie na osobę</template
                        >. Po założeniu profilu znajdziesz partnerkę do pary i
                        razem zaproponujecie grafik.
                    </p>
                </section>

                <section class="rounded-3xl bg-white p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-brand-green">
                        O stanowisku
                    </h2>
                    <p
                        class="mt-3 text-sm leading-relaxed whitespace-pre-line text-brand-green/80"
                    >
                        {{ offer.description || 'Brak opisu.' }}
                    </p>

                    <template
                        v-if="
                            offer.required_skills.length ||
                            offer.nice_to_have_skills.length
                        "
                    >
                        <h3 class="mt-6 font-semibold text-brand-green">
                            Umiejętności
                        </h3>
                        <div v-if="offer.required_skills.length" class="mt-3">
                            <p class="text-xs text-brand-green/80">Wymagane</p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <Chip
                                    v-for="skill in offer.required_skills"
                                    :key="skill"
                                    tone="dark"
                                    >{{ skill }}</Chip
                                >
                            </div>
                        </div>
                        <div
                            v-if="offer.nice_to_have_skills.length"
                            class="mt-3"
                        >
                            <p class="text-xs text-brand-green/80">
                                Mile widziane
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <Chip
                                    v-for="skill in offer.nice_to_have_skills"
                                    :key="skill"
                                    tone="outline"
                                    >{{ skill }}</Chip
                                >
                            </div>
                        </div>
                    </template>

                    <h3 class="mt-6 font-semibold text-brand-green">Warunki</h3>
                    <ul
                        class="mt-3 grid gap-2 text-sm text-brand-green sm:grid-cols-2"
                    >
                        <li
                            v-for="condition in conditions"
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

                <section class="rounded-3xl bg-white p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-brand-green">
                        Opinie rodziców o firmie {{ offer.company.name }}
                    </h2>
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
                    <div v-else class="mt-4 grid gap-3 md:grid-cols-2">
                        <figure
                            v-for="review in reviews"
                            :key="review.id"
                            class="rounded-2xl bg-brand-cream p-4"
                            data-test="offer-review"
                        >
                            <p
                                class="inline-flex items-center gap-1 text-sm font-semibold text-brand-green"
                            >
                                <Star
                                    class="size-4 fill-brand-yellow text-brand-yellow"
                                    aria-hidden="true"
                                />
                                {{ formatRating(review.overall) }} z 5
                            </p>
                            <blockquote
                                v-if="review.quote"
                                class="mt-2 text-sm text-brand-green"
                            >
                                {{ review.quote }}
                            </blockquote>
                            <figcaption
                                class="mt-2 text-xs text-brand-green/80"
                            >
                                {{ review.author_label || 'Anonimowo' }}
                            </figcaption>
                        </figure>
                    </div>
                </section>
            </div>

            <aside class="flex flex-col gap-4">
                <section
                    class="rounded-3xl bg-brand-green p-6 text-white"
                    data-test="offer-cta"
                >
                    <Sparkles class="size-5 text-brand-yellow" />
                    <template v-if="isCandidate">
                        <h2 class="mt-2 text-lg font-semibold">
                            Sprawdź, jak pasujesz
                        </h2>
                        <p class="mt-1 text-sm text-white/80">
                            Porównamy zatwierdzone umiejętności z wymaganiami
                            oferty i Twoim terminem powrotu.
                        </p>
                        <Link
                            :href="candidateOfferShow(offer.id)"
                            class="mt-4 inline-flex rounded-full bg-brand-peach px-5 py-2.5 text-sm font-semibold text-brand-green transition hover:bg-white"
                            data-test="candidate-match-link"
                        >
                            Zobacz dopasowanie
                        </Link>
                    </template>
                    <template v-else-if="isGuest">
                        <h2 class="mt-2 text-lg font-semibold">
                            Na ile pasujesz do tej oferty?
                        </h2>
                        <p class="mt-1 text-sm text-white/80">
                            Asystent AI porówna Twoje CV z wymaganiami i pokaże,
                            czy zdążysz na termin startu.
                        </p>
                        <Link
                            :href="register()"
                            class="mt-4 inline-flex rounded-full bg-brand-peach px-5 py-2.5 text-sm font-semibold text-brand-green transition hover:bg-white"
                            data-test="register-link"
                        >
                            Załóż profil, aby zobaczyć dopasowanie
                        </Link>
                    </template>
                    <template v-else>
                        <h2 class="mt-2 text-lg font-semibold">
                            Tak widzą tę ofertę kandydatki
                        </h2>
                        <p class="mt-1 text-sm text-white/80">
                            Kandydatki po zalogowaniu widzą też swoje
                            dopasowanie.
                        </p>
                    </template>
                </section>

                <section class="rounded-3xl bg-white p-6 text-brand-green">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">
                                {{ offer.company.name }}
                            </h2>
                            <p class="text-xs text-brand-green/80">
                                {{
                                    reviewCountLabel(offer.company.rating.count)
                                }}
                            </p>
                        </div>
                        <span class="text-2xl font-semibold">{{
                            formatRating(offer.company.rating.overall)
                        }}</span>
                    </div>
                    <div class="mt-5 space-y-3">
                        <RatingBar
                            v-for="category in categories"
                            :key="category"
                            :label="ratingCategoryLabels[category]"
                            :value="offer.company.rating.categories[category]"
                        />
                    </div>
                    <Link
                        :href="companyShow(offer.company.id)"
                        class="mt-5 inline-flex rounded-full border border-brand-green px-4 py-2 text-sm font-medium transition hover:bg-brand-cream"
                    >
                        O firmie
                    </Link>
                </section>
            </aside>
        </div>
    </div>
</template>
