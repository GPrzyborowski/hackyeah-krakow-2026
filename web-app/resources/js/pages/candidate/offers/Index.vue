<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bookmark,
    ChevronDown,
    Search,
    SlidersHorizontal,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { formatShortDate } from '@/components/candidate/format';
import OfferCard from '@/components/candidate/OfferCard.vue';
import type { CandidateOffer, Option } from '@/components/candidate/types';
import { cvAnalysis } from '@/routes/candidate';
import { index } from '@/routes/candidate/offers';
import { show as onboarding } from '@/routes/candidate/onboarding';

type Filters = {
    q: string;
    location: string;
    categories: string[];
    work_modes: string[];
    employment_fractions: string[];
    contract_types: string[];
    flexible_hours: boolean;
    childcare_subsidy: boolean;
    nursery_nearby: boolean;
    with_reviews: boolean;
    verified_only: boolean;
    job_share: boolean;
    saved: boolean;
    start_from: string | null;
    sort: 'match' | 'newest';
};

const props = defineProps<{
    offers: CandidateOffer[];
    filters: Filters;
    categories: Option[];
    workModes: Option[];
    employmentFractions: Option[];
    contractTypes: Option[];
    hasConfirmedSkills: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Oferty', href: index() }],
    },
});

const form = reactive<Filters>({
    ...props.filters,
    categories: [...props.filters.categories],
    work_modes: [...props.filters.work_modes],
    employment_fractions: [...props.filters.employment_fractions],
    contract_types: [...props.filters.contract_types],
});
const showFiltersOnMobile = ref(false);

function apply() {
    router.get(
        index.url(),
        {
            q: form.q || undefined,
            location: form.location || undefined,
            categories: form.categories.length ? form.categories : undefined,
            work_modes: form.work_modes.length ? form.work_modes : undefined,
            employment_fractions: form.employment_fractions.length
                ? form.employment_fractions
                : undefined,
            contract_types: form.contract_types.length
                ? form.contract_types
                : undefined,
            flexible_hours: form.flexible_hours ? 1 : undefined,
            childcare_subsidy: form.childcare_subsidy ? 1 : undefined,
            nursery_nearby: form.nursery_nearby ? 1 : undefined,
            with_reviews: form.with_reviews ? 1 : undefined,
            verified_only: form.verified_only ? 1 : undefined,
            job_share: form.job_share ? 1 : undefined,
            saved: form.saved ? 1 : undefined,
            start_from: form.start_from ?? '',
            sort: form.sort === 'match' ? undefined : form.sort,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

type ToggleFilter =
    | 'flexible_hours'
    | 'childcare_subsidy'
    | 'nursery_nearby'
    | 'with_reviews'
    | 'verified_only'
    | 'job_share';

const parentFilters: { key: ToggleFilter; label: string }[] = [
    { key: 'flexible_hours', label: 'Elastyczne godziny' },
    { key: 'nursery_nearby', label: 'Żłobek lub przedszkole w pobliżu' },
    {
        key: 'childcare_subsidy',
        label: 'Dofinansowanie żłobka lub przedszkola',
    },
    { key: 'job_share', label: 'Job sharing (dwie osoby)' },
];

const companyFilters: { key: ToggleFilter; label: string }[] = [
    { key: 'with_reviews', label: 'Firma z opiniami rodziców' },
    { key: 'verified_only', label: 'Tylko zweryfikowane firmy' },
];

function countToggles(toggles: { key: ToggleFilter }[]): number {
    return toggles.filter((toggle) => form[toggle.key]).length;
}

const sectionCounts = computed(() => ({
    category: form.categories.length,
    workMode: form.work_modes.length,
    fraction: form.employment_fractions.length,
    contractType: form.contract_types.length,
    parents: countToggles(parentFilters),
    company: countToggles(companyFilters),
    start: form.start_from ? 1 : 0,
}));

/**
 * Sections start expanded when they hold an active filter; industry, work mode, fraction and contract type are always open.
 */
const initiallyOpen = {
    parents: sectionCounts.value.parents > 0,
    company: sectionCounts.value.company > 0,
    start: sectionCounts.value.start > 0,
};

function labelFor(options: Option[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

const activeChips = computed(() => {
    const chips: { key: string; label: string; clear: () => void }[] = [];

    if (form.q) {
        chips.push({
            key: 'q',
            label: `„${form.q}”`,
            clear: () => (form.q = ''),
        });
    }

    if (form.location) {
        chips.push({
            key: 'location',
            label: form.location,
            clear: () => (form.location = ''),
        });
    }

    form.categories.forEach((value) =>
        chips.push({
            key: `category-${value}`,
            label: labelFor(props.categories, value),
            clear: () =>
                (form.categories = form.categories.filter(
                    (category) => category !== value,
                )),
        }),
    );

    form.work_modes.forEach((value) =>
        chips.push({
            key: `work_mode-${value}`,
            label: labelFor(props.workModes, value),
            clear: () =>
                (form.work_modes = form.work_modes.filter(
                    (mode) => mode !== value,
                )),
        }),
    );

    form.employment_fractions.forEach((value) =>
        chips.push({
            key: `fraction-${value}`,
            label: labelFor(props.employmentFractions, value),
            clear: () =>
                (form.employment_fractions = form.employment_fractions.filter(
                    (fraction) => fraction !== value,
                )),
        }),
    );

    form.contract_types.forEach((value) =>
        chips.push({
            key: `contract_type-${value}`,
            label: labelFor(props.contractTypes, value),
            clear: () =>
                (form.contract_types = form.contract_types.filter(
                    (contractType) => contractType !== value,
                )),
        }),
    );

    [...parentFilters, ...companyFilters]
        .filter((toggle) => form[toggle.key])
        .forEach((toggle) =>
            chips.push({
                key: toggle.key,
                label: toggle.label,
                clear: () => (form[toggle.key] = false),
            }),
        );

    if (form.start_from) {
        chips.push({
            key: 'start_from',
            label: `Start od ${formatShortDate(form.start_from)}`,
            clear: () => (form.start_from = null),
        });
    }

    return chips;
});

const activeFilterCount = computed(() => activeChips.value.length);

const hasActiveFilters = computed(() => activeFilterCount.value > 0);

function removeChip(clear: () => void): void {
    clear();
    apply();
}

/**
 * Clears every filter except the "Zapisane" view and the sort order.
 */
function resetFilters(): void {
    form.q = '';
    form.location = '';
    form.categories = [];
    form.work_modes = [];
    form.employment_fractions = [];
    form.contract_types = [];
    form.flexible_hours = false;
    form.childcare_subsidy = false;
    form.nursery_nearby = false;
    form.with_reviews = false;
    form.verified_only = false;
    form.job_share = false;
    form.start_from = null;
    apply();
}
</script>

<template>
    <Head title="Oferty" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <h1
            class="text-3xl leading-tight font-extrabold tracking-tight text-brand-green md:text-5xl"
        >
            Oferty dopasowane do Twojego terminu
        </h1>

        <form
            class="flex flex-col gap-2 rounded-3xl bg-white p-2 shadow-sm md:flex-row md:items-center md:rounded-full"
            role="search"
            @submit.prevent="apply"
        >
            <label
                class="flex flex-1 items-center gap-2 rounded-full px-4 focus-within:ring-2 focus-within:ring-brand-green"
            >
                <Search class="size-4 text-brand-green/80" aria-hidden="true" />
                <input
                    v-model="form.q"
                    type="search"
                    placeholder="Stanowisko lub umiejętność"
                    aria-label="Stanowisko lub umiejętność"
                    class="w-full bg-transparent py-2 text-sm text-brand-green outline-none placeholder:text-brand-green/70"
                />
            </label>
            <div class="hidden h-6 w-px bg-brand-cream md:block" />
            <label
                class="flex flex-1 items-center gap-2 rounded-full px-4 focus-within:ring-2 focus-within:ring-brand-green"
            >
                <input
                    v-model="form.location"
                    type="search"
                    placeholder="Miasto lub zdalnie"
                    aria-label="Miasto lub zdalnie"
                    class="w-full bg-transparent py-2 text-sm text-brand-green outline-none placeholder:text-brand-green/70"
                />
            </label>
            <button
                type="submit"
                class="rounded-full bg-brand-green px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft"
            >
                Szukaj ofert
            </button>
        </form>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[16rem_1fr]">
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-full bg-white px-5 py-3 text-sm font-semibold text-brand-green shadow-sm lg:hidden"
                    :aria-expanded="showFiltersOnMobile"
                    aria-controls="candidate-offer-filters"
                    @click="showFiltersOnMobile = !showFiltersOnMobile"
                >
                    <span class="flex items-center gap-2">
                        Filtry
                        <span
                            v-if="activeFilterCount"
                            class="rounded-full bg-brand-green px-2 py-0.5 text-xs text-white"
                            >{{ activeFilterCount }}</span
                        >
                    </span>
                    <SlidersHorizontal class="size-4" />
                </button>

                <div
                    id="candidate-offer-filters"
                    class="mt-2 rounded-3xl bg-white p-2 text-sm text-brand-green shadow-sm lg:mt-0 lg:block"
                    :class="showFiltersOnMobile ? 'block' : 'hidden'"
                >
                    <div class="flex items-center justify-between px-3 pt-3 pb-1">
                        <h2 class="text-lg font-bold">Filtry</h2>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="text-xs font-medium underline underline-offset-2"
                            @click="resetFilters"
                        >
                            Wyczyść
                        </button>
                    </div>

                    <details open class="group/section rounded-2xl">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl px-3 py-3 font-semibold hover:bg-brand-cream [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center gap-2">
                                Branża
                                <span
                                    v-if="sectionCounts.category"
                                    class="rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs"
                                    >{{ sectionCounts.category }}</span
                                >
                            </span>
                            <ChevronDown
                                class="size-4 transition group-open/section:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <fieldset class="px-3 pb-3">
                            <legend class="sr-only">Branża</legend>
                            <label
                                v-for="category in categories"
                                :key="category.value"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-brand-cream"
                            >
                                <input
                                    v-model="form.categories"
                                    type="checkbox"
                                    :value="category.value"
                                    class="size-4 shrink-0 accent-brand-green"
                                    :data-test="`filter-category-${category.value}`"
                                    @change="apply"
                                />
                                {{ category.label }}
                            </label>
                        </fieldset>
                    </details>

                    <details open class="group/section rounded-2xl">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl px-3 py-3 font-semibold hover:bg-brand-cream [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center gap-2">
                                Tryb pracy
                                <span
                                    v-if="sectionCounts.workMode"
                                    class="rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs"
                                    >{{ sectionCounts.workMode }}</span
                                >
                            </span>
                            <ChevronDown
                                class="size-4 transition group-open/section:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <fieldset class="px-3 pb-3">
                            <legend class="sr-only">Tryb pracy</legend>
                            <label
                                v-for="mode in workModes"
                                :key="mode.value"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-brand-cream"
                            >
                                <input
                                    v-model="form.work_modes"
                                    type="checkbox"
                                    :value="mode.value"
                                    class="size-4 shrink-0 accent-brand-green"
                                    @change="apply"
                                />
                                {{ mode.label }}
                            </label>
                        </fieldset>
                    </details>

                    <details open class="group/section rounded-2xl">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl px-3 py-3 font-semibold hover:bg-brand-cream [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center gap-2">
                                Wymiar etatu
                                <span
                                    v-if="sectionCounts.fraction"
                                    class="rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs"
                                    >{{ sectionCounts.fraction }}</span
                                >
                            </span>
                            <ChevronDown
                                class="size-4 transition group-open/section:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <fieldset class="px-3 pb-3">
                            <legend class="sr-only">Wymiar etatu</legend>
                            <label
                                v-for="fraction in employmentFractions"
                                :key="fraction.value"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-brand-cream"
                            >
                                <input
                                    v-model="form.employment_fractions"
                                    type="checkbox"
                                    :value="fraction.value"
                                    class="size-4 shrink-0 accent-brand-green"
                                    @change="apply"
                                />
                                {{ fraction.label }}
                            </label>
                        </fieldset>
                    </details>

                    <details open class="group/section rounded-2xl">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl px-3 py-3 font-semibold hover:bg-brand-cream [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center gap-2">
                                Forma zatrudnienia
                                <span
                                    v-if="sectionCounts.contractType"
                                    class="rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs"
                                    >{{ sectionCounts.contractType }}</span
                                >
                            </span>
                            <ChevronDown
                                class="size-4 transition group-open/section:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <fieldset class="px-3 pb-3">
                            <legend class="sr-only">Forma zatrudnienia</legend>
                            <label
                                v-for="contractType in contractTypes"
                                :key="contractType.value"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-brand-cream"
                            >
                                <input
                                    v-model="form.contract_types"
                                    type="checkbox"
                                    :value="contractType.value"
                                    class="size-4 shrink-0 accent-brand-green"
                                    :data-test="`filter-contract-type-${contractType.value}`"
                                    @change="apply"
                                />
                                {{ contractType.label }}
                            </label>
                        </fieldset>
                    </details>

                    <details
                        :open="initiallyOpen.parents"
                        class="group/section rounded-2xl"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl px-3 py-3 font-semibold hover:bg-brand-cream [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center gap-2">
                                Dla rodziców
                                <span
                                    v-if="sectionCounts.parents"
                                    class="rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs"
                                    >{{ sectionCounts.parents }}</span
                                >
                            </span>
                            <ChevronDown
                                class="size-4 transition group-open/section:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <fieldset class="px-3 pb-3">
                            <legend class="sr-only">Dla rodziców</legend>
                            <label
                                v-for="filter in parentFilters"
                                :key="filter.key"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-brand-cream"
                            >
                                <input
                                    v-model="form[filter.key]"
                                    type="checkbox"
                                    class="size-4 shrink-0 accent-brand-green"
                                    @change="apply"
                                />
                                {{ filter.label }}
                            </label>
                        </fieldset>
                    </details>

                    <details
                        :open="initiallyOpen.company"
                        class="group/section rounded-2xl"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl px-3 py-3 font-semibold hover:bg-brand-cream [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center gap-2">
                                Firma
                                <span
                                    v-if="sectionCounts.company"
                                    class="rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs"
                                    >{{ sectionCounts.company }}</span
                                >
                            </span>
                            <ChevronDown
                                class="size-4 transition group-open/section:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <fieldset class="px-3 pb-3">
                            <legend class="sr-only">Firma</legend>
                            <label
                                v-for="filter in companyFilters"
                                :key="filter.key"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-brand-cream"
                            >
                                <input
                                    v-model="form[filter.key]"
                                    type="checkbox"
                                    class="size-4 shrink-0 accent-brand-green"
                                    @change="apply"
                                />
                                {{ filter.label }}
                            </label>
                        </fieldset>
                    </details>

                    <details
                        :open="initiallyOpen.start"
                        class="group/section rounded-2xl"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl px-3 py-3 font-semibold hover:bg-brand-cream [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center gap-2">
                                Termin startu
                                <span
                                    v-if="sectionCounts.start"
                                    class="rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs"
                                    >{{ sectionCounts.start }}</span
                                >
                            </span>
                            <ChevronDown
                                class="size-4 transition group-open/section:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <div class="px-3 pb-3">
                            <label for="start_from" class="sr-only"
                                >Mogę zacząć od</label
                            >
                            <input
                                id="start_from"
                                v-model="form.start_from"
                                type="date"
                                class="w-full rounded-2xl border border-brand-mint-soft px-3 py-2 text-sm text-brand-green"
                                aria-describedby="start_from-hint"
                                @change="apply"
                            />
                            <p
                                id="start_from-hint"
                                class="mt-1.5 text-xs text-brand-green/80"
                            >
                                Mogę zacząć od tej daty. Pokazujemy oferty, do
                                których zdążysz (do 30 dni po starcie).
                            </p>
                        </div>
                    </details>
                </div>
            </aside>

            <section class="flex min-w-0 flex-col gap-4">
                <div
                    class="flex flex-col gap-4 rounded-3xl bg-brand-green p-6 text-white md:flex-row md:items-center"
                >
                    <div class="flex-1">
                        <h2 class="flex items-center gap-2 text-lg font-bold">
                            <Sparkles class="size-5 text-brand-yellow" />
                            Dopasowane do Twojego CV
                        </h2>
                        <p class="mt-1 text-sm text-white/80">
                            Porównaliśmy Twoje zatwierdzone umiejętności z
                            ofertami i ułożyliśmy je od najlepiej pasujących.
                        </p>
                    </div>
                    <Link
                        :href="
                            hasConfirmedSkills
                                ? cvAnalysis()
                                : onboarding({ query: { step: 2 } })
                        "
                        data-test="cv-analysis-link"
                        class="self-start rounded-full bg-brand-peach px-5 py-2 text-sm font-semibold text-brand-green md:self-auto"
                    >
                        {{
                            hasConfirmedSkills
                                ? 'Zobacz analizę CV'
                                : 'Dodaj umiejętności'
                        }}
                    </Link>
                </div>

                <ul
                    v-if="activeChips.length"
                    class="flex flex-wrap items-center gap-2"
                    aria-label="Aktywne filtry"
                >
                    <li v-for="chip in activeChips" :key="chip.key">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-green-soft"
                            @click="removeChip(chip.clear)"
                        >
                            {{ chip.label }}
                            <X class="size-3.5" aria-hidden="true" />
                            <span class="sr-only">– usuń filtr</span>
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-2 text-xs font-medium text-brand-green underline underline-offset-2"
                            @click="resetFilters"
                        >
                            Wyczyść wszystko
                        </button>
                    </li>
                </ul>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm font-semibold text-brand-green">
                            {{
                                form.saved
                                    ? 'Zapisane oferty'
                                    : 'Pasujące oferty'
                            }}
                            ({{ offers.length }})
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-semibold transition-colors"
                            :class="
                                form.saved
                                    ? 'border-brand-green bg-brand-green text-white'
                                    : 'border-brand-mint-soft bg-white text-brand-green hover:bg-brand-cream'
                            "
                            :aria-pressed="form.saved"
                            data-test="saved-filter"
                            @click="
                                form.saved = !form.saved;
                                apply();
                            "
                        >
                            <Bookmark class="size-4" /> Zapisane
                        </button>
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-brand-green/80"
                    >
                        Sortuj
                        <select
                            v-model="form.sort"
                            class="rounded-full border border-brand-mint-soft bg-white px-3 py-1.5 text-sm text-brand-green"
                            @change="apply"
                        >
                            <option value="match">Najlepsze dopasowanie</option>
                            <option value="newest">Najnowsze</option>
                        </select>
                    </label>
                </div>

                <OfferCard
                    v-for="offer in offers"
                    :key="offer.id"
                    :offer="offer"
                />

                <div
                    v-if="offers.length === 0"
                    class="rounded-3xl bg-white p-8 text-center text-brand-green"
                >
                    <p class="font-semibold">
                        Brak ofert dla wybranych filtrów.
                    </p>
                    <p class="mt-1 text-sm text-brand-green/80">
                        Spróbuj zmienić datę startu albo usuń część filtrów.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
