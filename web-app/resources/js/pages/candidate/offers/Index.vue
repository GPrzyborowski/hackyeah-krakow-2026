<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Bookmark, Search, SlidersHorizontal, Sparkles } from '@lucide/vue';
import { reactive, ref } from 'vue';
import OfferCard from '@/components/candidate/OfferCard.vue';
import type { CandidateOffer, Option } from '@/components/candidate/types';
import { cvAnalysis } from '@/routes/candidate';
import { index } from '@/routes/candidate/offers';
import { show as onboarding } from '@/routes/candidate/onboarding';

type Filters = {
    q: string;
    location: string;
    work_modes: string[];
    employment_fractions: string[];
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
    workModes: Option[];
    employmentFractions: Option[];
    hasConfirmedSkills: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Oferty', href: index() }],
    },
});

const form = reactive<Filters>({ ...props.filters });
const showFiltersOnMobile = ref(false);

function apply() {
    router.get(
        index.url(),
        {
            q: form.q || undefined,
            location: form.location || undefined,
            work_modes: form.work_modes.length ? form.work_modes : undefined,
            employment_fractions: form.employment_fractions.length
                ? form.employment_fractions
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

const parentFilters: {
    key:
        | 'flexible_hours'
        | 'childcare_subsidy'
        | 'nursery_nearby'
        | 'with_reviews'
        | 'verified_only'
        | 'job_share';
    label: string;
}[] = [
    { key: 'flexible_hours', label: 'Elastyczne godziny' },
    { key: 'nursery_nearby', label: 'Żłobek lub przedszkole w pobliżu' },
    { key: 'childcare_subsidy', label: 'Dofinansowanie żłobka lub przedszkola' },
    { key: 'with_reviews', label: 'Firma z opiniami rodziców' },
    { key: 'verified_only', label: 'Tylko zweryfikowane firmy' },
    { key: 'job_share', label: 'Job sharing (dwie osoby)' },
];
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

        <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
            <aside>
                <button
                    type="button"
                    class="mb-3 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-brand-green shadow-sm lg:hidden"
                    @click="showFiltersOnMobile = !showFiltersOnMobile"
                >
                    <SlidersHorizontal class="size-4" /> Filtry
                </button>
                <div
                    class="space-y-6 rounded-3xl bg-white p-5 shadow-sm lg:block"
                    :class="showFiltersOnMobile ? 'block' : 'hidden'"
                >
                    <h2
                        class="hidden text-lg font-bold text-brand-green lg:block"
                    >
                        Filtry
                    </h2>

                    <fieldset class="space-y-2">
                        <legend
                            class="mb-2 text-sm font-semibold text-brand-green"
                        >
                            Tryb pracy
                        </legend>
                        <label
                            v-for="mode in workModes"
                            :key="mode.value"
                            class="flex items-center gap-2 text-sm text-brand-green"
                        >
                            <input
                                v-model="form.work_modes"
                                type="checkbox"
                                :value="mode.value"
                                class="size-4 accent-brand-green"
                                @change="apply"
                            />
                            {{ mode.label }}
                        </label>
                    </fieldset>

                    <fieldset class="space-y-2">
                        <legend
                            class="mb-2 text-sm font-semibold text-brand-green"
                        >
                            Wymiar etatu
                        </legend>
                        <label
                            v-for="fraction in employmentFractions"
                            :key="fraction.value"
                            class="flex items-center gap-2 text-sm text-brand-green"
                        >
                            <input
                                v-model="form.employment_fractions"
                                type="checkbox"
                                :value="fraction.value"
                                class="size-4 accent-brand-green"
                                @change="apply"
                            />
                            {{ fraction.label }}
                        </label>
                    </fieldset>

                    <fieldset class="space-y-2">
                        <legend
                            class="mb-2 text-sm font-semibold text-brand-green"
                        >
                            Dla rodziców
                        </legend>
                        <label
                            v-for="filter in parentFilters"
                            :key="filter.key"
                            class="flex items-center gap-2 text-sm text-brand-green"
                        >
                            <input
                                v-model="form[filter.key]"
                                type="checkbox"
                                class="size-4 accent-brand-green"
                                @change="apply"
                            />
                            {{ filter.label }}
                        </label>
                    </fieldset>

                    <div class="space-y-2">
                        <label
                            for="start_from"
                            class="block text-sm font-semibold text-brand-green"
                        >
                            Mogę zacząć od
                        </label>
                        <input
                            id="start_from"
                            v-model="form.start_from"
                            type="date"
                            class="w-full rounded-2xl border border-brand-mint-soft px-3 py-2 text-sm text-brand-green"
                            @change="apply"
                        />
                        <p class="text-xs text-brand-green/80">
                            Pokazujemy oferty, do których zdążysz (do 30 dni po
                            starcie).
                        </p>
                    </div>
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
