<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarDays,
    MapPin,
    Plus,
    Users,
    UsersRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CandidateController from '@/actions/App/Http/Controllers/Employer/CandidateController';
import JobOfferController from '@/actions/App/Http/Controllers/Employer/JobOfferController';
import EmployerPairController from '@/actions/App/Http/Controllers/JobSharing/EmployerPairController';
import {
    formatShortDate,
    formatSalaryRange,
    pluralizeCandidates,
} from '@/components/employer/format';
import type {
    EmployerOffer,
    OfferStatistics,
    OfferStatus,
} from '@/components/employer/types';
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';

type OfferRow = EmployerOffer & {
    statistics: OfferStatistics;
    is_parent_friendly: boolean;
    submitted_pairs_count: number;
};

const props = defineProps<{
    offers: OfferRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ogłoszenia', href: JobOfferController.index() },
        ],
    },
});

const tabs: { value: OfferStatus | 'all'; label: string }[] = [
    { value: 'all', label: 'Wszystkie' },
    { value: 'published', label: 'Opublikowane' },
    { value: 'draft', label: 'Szkice' },
    { value: 'closed', label: 'Zamknięte' },
];

const statusLabels: Record<OfferStatus, string> = {
    published: 'Opublikowana',
    draft: 'Szkic',
    closed: 'Zamknięta',
};

const activeTab = ref<OfferStatus | 'all'>('all');

const visibleOffers = computed(() =>
    activeTab.value === 'all'
        ? props.offers
        : props.offers.filter((offer) => offer.status === activeTab.value),
);

function countFor(tab: OfferStatus | 'all'): number {
    return tab === 'all'
        ? props.offers.length
        : props.offers.filter((offer) => offer.status === tab).length;
}

const closingOfferId = ref<number | null>(null);

function closeOffer(offer: OfferRow): void {
    router.post(
        JobOfferController.close.url(offer.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (closingOfferId.value = null),
        },
    );
}
</script>

<template>
    <Head title="Ogłoszenia" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-brand-green sm:text-4xl">
                    Twoje ogłoszenia
                </h1>
                <p class="mt-2 text-sm text-brand-green/80">
                    Publikuj oferty i przeglądaj kandydatki, które mogą zacząć w
                    Twoim terminie.
                </p>
            </div>
            <Link
                :href="JobOfferController.create()"
                class="inline-flex h-11 items-center gap-2 rounded-full bg-brand-green px-5 text-sm font-semibold text-white hover:bg-brand-green-soft"
            >
                <Plus class="size-4" /> Nowe ogłoszenie
            </Link>
        </div>

        <div class="mt-6 flex flex-wrap gap-2" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="activeTab === tab.value"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                :class="
                    activeTab === tab.value
                        ? 'bg-brand-green text-white'
                        : 'border border-brand-green/20 bg-white text-brand-green hover:bg-brand-mint-soft'
                "
                @click="activeTab = tab.value"
            >
                {{ tab.label }} · {{ countFor(tab.value) }}
            </button>
        </div>

        <div
            v-if="visibleOffers.length === 0"
            class="mt-6 rounded-3xl bg-white p-10 text-center shadow-sm"
        >
            <p class="text-lg font-semibold text-brand-green">
                Nie masz tu jeszcze ogłoszeń
            </p>
            <p class="mt-1 text-sm text-brand-green/70">
                Dodaj ofertę, a pokażemy Ci pasujące kandydatki.
            </p>
        </div>

        <div v-else class="mt-6 grid gap-4 md:grid-cols-2">
            <article
                v-for="offer in visibleOffers"
                :key="offer.id"
                class="flex flex-col rounded-3xl bg-white p-6 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-green">
                            {{ offer.title }}
                        </h2>
                        <p
                            class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-brand-green/70"
                        >
                            <span
                                v-if="offer.city"
                                class="inline-flex items-center gap-1"
                                ><MapPin class="size-3.5" />{{
                                    offer.city
                                }}</span
                            >
                            <span class="inline-flex items-center gap-1"
                                ><CalendarDays class="size-3.5" />od
                                {{ formatShortDate(offer.start_date) }}</span
                            >
                            <span>{{ offer.employment_fraction_label }}</span>
                            <span>{{ offer.work_mode_label }}</span>
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                        :class="{
                            'bg-brand-yellow text-brand-green':
                                offer.status === 'published',
                            'bg-brand-mint-soft text-brand-green':
                                offer.status === 'draft',
                            'bg-neutral-200 text-neutral-600':
                                offer.status === 'closed',
                        }"
                    >
                        {{ statusLabels[offer.status] }}
                    </span>
                </div>

                <p
                    v-if="formatSalaryRange(offer.salary_min, offer.salary_max)"
                    class="mt-3 text-sm font-medium text-brand-green"
                >
                    {{ formatSalaryRange(offer.salary_min, offer.salary_max) }}
                </p>
                <p
                    v-if="offer.is_parent_friendly"
                    class="mt-2 inline-flex w-fit items-center gap-1 rounded-full bg-brand-mint-soft px-3 py-1 text-xs font-medium text-brand-green"
                >
                    <BadgeCheck class="size-3.5" /> Przyjazna rodzicom
                </p>
                <JobShareChip
                    v-if="offer.is_job_share"
                    class="mt-2 w-fit"
                    :hours-per-person="offer.hours_per_person"
                />

                <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-brand-cream p-3">
                        <dt class="text-[11px] text-brand-green/70">
                            Pasujące
                        </dt>
                        <dd class="text-xl font-bold text-brand-green">
                            {{ offer.statistics.matched_count }}
                        </dd>
                    </div>
                    <div class="rounded-2xl bg-brand-cream p-3">
                        <dt class="text-[11px] text-brand-green/70">
                            Do przejrzenia
                        </dt>
                        <dd class="text-xl font-bold text-brand-green">
                            {{ offer.statistics.to_review_count }}
                        </dd>
                    </div>
                    <div class="rounded-2xl bg-brand-cream p-3">
                        <dt class="text-[11px] text-brand-green/70">
                            Zaproszone
                        </dt>
                        <dd class="text-xl font-bold text-brand-green">
                            {{ offer.statistics.invited_count }}
                        </dd>
                        <dd class="text-[11px] text-brand-green/70">
                            {{ offer.statistics.accepted_count }} przyjęło
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <Link
                        v-if="offer.status === 'published'"
                        :href="
                            CandidateController.index({
                                query: { offer: offer.id },
                            })
                        "
                        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-green px-4 text-sm font-semibold text-white hover:bg-brand-green-soft"
                    >
                        <Users class="size-4" />
                        {{ offer.statistics.to_review_count }}
                        {{
                            pluralizeCandidates(
                                offer.statistics.to_review_count,
                            )
                        }}
                        do przejrzenia
                    </Link>
                    <Link
                        v-if="
                            offer.is_job_share && offer.status === 'published'
                        "
                        :href="EmployerPairController.index(offer.id)"
                        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-yellow px-4 text-sm font-semibold text-brand-green hover:bg-brand-yellow/80"
                        data-test="job-share-pairs-link"
                    >
                        <UsersRound class="size-4" />
                        Pary job-sharing · {{ offer.submitted_pairs_count }}
                    </Link>
                    <Link
                        :href="JobOfferController.edit(offer.id)"
                        class="inline-flex h-9 items-center rounded-full border border-brand-green/30 px-4 text-sm font-medium text-brand-green hover:bg-brand-mint-soft"
                    >
                        Edytuj
                    </Link>
                    <template v-if="offer.status !== 'closed'">
                        <button
                            v-if="closingOfferId !== offer.id"
                            type="button"
                            class="inline-flex h-9 items-center rounded-full px-3 text-sm text-brand-green/70 hover:text-brand-green"
                            @click="closingOfferId = offer.id"
                        >
                            Zamknij
                        </button>
                        <span
                            v-else
                            class="inline-flex items-center gap-2 text-sm text-brand-green"
                        >
                            Zamknąć ofertę?
                            <button
                                type="button"
                                class="rounded-full bg-brand-peach px-3 py-1 font-semibold"
                                @click="closeOffer(offer)"
                            >
                                Tak
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-2 py-1 text-brand-green/70"
                                @click="closingOfferId = null"
                            >
                                Nie
                            </button>
                        </span>
                    </template>
                </div>
            </article>
        </div>
    </div>
</template>
