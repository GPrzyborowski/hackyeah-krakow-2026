<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, SlidersHorizontal } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import OfferCard from '@/components/brand/OfferCard.vue';
import type { PublicOffer } from '@/components/brand/types';
import { register } from '@/routes';
import { index as offersIndex } from '@/routes/public/offers';

type Option = { value: string; label: string };

type Filters = {
    q: string;
    location: string;
    work_mode: string[];
    fraction: string[];
    flexible: boolean;
    job_share: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    offers: Paginated<PublicOffer>;
    filters: Filters;
    workModes: Option[];
    fractions: Option[];
}>();

const page = usePage();

const isCandidate = computed(() => page.props.auth.role === 'candidate');
const isGuest = computed(() => !page.props.auth.user);

const form = reactive<Filters>({
    q: props.filters.q,
    location: props.filters.location,
    work_mode: [...props.filters.work_mode],
    fraction: [...props.filters.fraction],
    flexible: props.filters.flexible,
    job_share: props.filters.job_share,
});

const areFiltersOpen = ref(false);

const hasActiveFilters = computed(
    () =>
        form.q !== '' ||
        form.location !== '' ||
        form.work_mode.length > 0 ||
        form.fraction.length > 0 ||
        form.flexible ||
        form.job_share,
);

function applyFilters(): void {
    router.get(
        offersIndex.url(),
        {
            q: form.q || undefined,
            location: form.location || undefined,
            work_mode: form.work_mode.length ? form.work_mode : undefined,
            fraction: form.fraction.length ? form.fraction : undefined,
            flexible: form.flexible ? 1 : undefined,
            job_share: form.job_share ? 1 : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters(): void {
    form.q = '';
    form.location = '';
    form.work_mode = [];
    form.fraction = [];
    form.flexible = false;
    form.job_share = false;
    applyFilters();
}

const offerCountLabel = computed(() => {
    const total = props.offers.total;

    if (total === 1) {
        return '1 oferta';
    }

    const lastDigit = total % 10;
    const lastTwoDigits = total % 100;

    return lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14)
        ? `${total} oferty`
        : `${total} ofert`;
});
</script>

<template>
    <Head title="Oferty pracy" />

    <div class="mx-auto max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
        <h1
            class="text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl lg:text-5xl"
        >
            Oferty od firm, które czekają na Twój powrót
        </h1>

        <form
            class="mt-6 flex flex-col gap-2 rounded-3xl bg-white p-2 sm:flex-row sm:items-center sm:rounded-full"
            role="search"
            @submit.prevent="applyFilters"
        >
            <label class="sr-only" for="offer-search"
                >Stanowisko lub umiejętność</label
            >
            <input
                id="offer-search"
                v-model="form.q"
                type="search"
                name="q"
                placeholder="Stanowisko, firma lub umiejętność"
                class="min-w-0 flex-1 rounded-full bg-transparent px-4 py-2.5 text-sm text-brand-green placeholder:text-brand-green/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
            />
            <span class="hidden h-6 w-px bg-brand-green/15 sm:block" />
            <label class="sr-only" for="offer-location"
                >Miasto lub zdalnie</label
            >
            <input
                id="offer-location"
                v-model="form.location"
                type="search"
                name="location"
                placeholder="Miasto lub „zdalnie”"
                class="min-w-0 flex-1 rounded-full bg-transparent px-4 py-2.5 text-sm text-brand-green placeholder:text-brand-green/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
            />
            <button
                type="submit"
                class="rounded-full bg-brand-green px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-green-soft"
            >
                Szukaj ofert
            </button>
        </form>

        <div class="mt-6 grid gap-6 lg:grid-cols-[16rem_1fr]">
            <aside>
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-full bg-white px-5 py-3 text-sm font-semibold text-brand-green lg:hidden"
                    :aria-expanded="areFiltersOpen"
                    aria-controls="offer-filters"
                    @click="areFiltersOpen = !areFiltersOpen"
                >
                    Filtry
                    <SlidersHorizontal class="size-4" />
                </button>

                <div
                    id="offer-filters"
                    class="mt-2 rounded-3xl bg-white p-5 text-sm text-brand-green lg:mt-0 lg:block"
                    :class="areFiltersOpen ? 'block' : 'hidden'"
                >
                    <h2 class="text-lg font-semibold">Filtry</h2>

                    <fieldset class="mt-5">
                        <legend class="font-semibold">Tryb pracy</legend>
                        <label
                            v-for="mode in workModes"
                            :key="mode.value"
                            class="mt-2.5 flex cursor-pointer items-center gap-2.5"
                        >
                            <input
                                v-model="form.work_mode"
                                type="checkbox"
                                :value="mode.value"
                                class="size-4 accent-brand-green"
                                @change="applyFilters"
                            />
                            {{ mode.label }}
                        </label>
                    </fieldset>

                    <fieldset class="mt-6">
                        <legend class="font-semibold">Wymiar etatu</legend>
                        <label
                            v-for="fraction in fractions"
                            :key="fraction.value"
                            class="mt-2.5 flex cursor-pointer items-center gap-2.5"
                        >
                            <input
                                v-model="form.fraction"
                                type="checkbox"
                                :value="fraction.value"
                                class="size-4 accent-brand-green"
                                @change="applyFilters"
                            />
                            {{ fraction.label }}
                        </label>
                    </fieldset>

                    <fieldset class="mt-6">
                        <legend class="font-semibold">Dla rodziców</legend>
                        <label
                            class="mt-2.5 flex cursor-pointer items-center gap-2.5"
                        >
                            <input
                                v-model="form.flexible"
                                type="checkbox"
                                class="size-4 accent-brand-green"
                                data-test="filter-flexible"
                                @change="applyFilters"
                            />
                            Elastyczne godziny
                        </label>
                        <label
                            class="mt-2.5 flex cursor-pointer items-center gap-2.5"
                        >
                            <input
                                v-model="form.job_share"
                                type="checkbox"
                                class="size-4 accent-brand-green"
                                data-test="filter-job-share"
                                @change="applyFilters"
                            />
                            Job sharing (dwie osoby)
                        </label>
                    </fieldset>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="mt-6 text-xs font-medium underline underline-offset-2"
                        @click="resetFilters"
                    >
                        Wyczyść filtry
                    </button>
                </div>
            </aside>

            <div class="min-w-0">
                <div
                    v-if="isGuest || isCandidate"
                    class="flex flex-col gap-4 rounded-3xl bg-brand-green p-5 text-white sm:flex-row sm:items-center sm:justify-between sm:p-6"
                >
                    <div>
                        <p class="text-lg font-semibold">
                            {{
                                isCandidate
                                    ? 'Twoje dopasowania czekają w panelu'
                                    : 'Załóż profil, a pokażemy dopasowanie do Twojego CV'
                            }}
                        </p>
                        <p class="mt-1 text-xs text-white/70">
                            Asystent AI porówna Twoje umiejętności z ofertami i
                            pokaże, które pasują najlepiej.
                        </p>
                    </div>
                    <Link
                        v-if="isCandidate"
                        href="/candidate/offers"
                        class="w-fit shrink-0 rounded-full bg-brand-peach px-5 py-2.5 text-sm font-semibold text-brand-green transition hover:bg-white"
                        data-test="candidate-offers-link"
                    >
                        Zobacz dopasowane oferty
                    </Link>
                    <Link
                        v-else
                        :href="register()"
                        class="w-fit shrink-0 rounded-full bg-brand-peach px-5 py-2.5 text-sm font-semibold text-brand-green transition hover:bg-white"
                    >
                        Załóż profil
                    </Link>
                </div>

                <p class="mt-6 text-sm font-medium text-brand-green">
                    {{ offerCountLabel }}
                </p>

                <div v-if="offers.data.length" class="mt-3 space-y-4">
                    <OfferCard
                        v-for="offer in offers.data"
                        :key="offer.id"
                        :offer="offer"
                    />
                </div>
                <div
                    v-else
                    class="mt-3 rounded-3xl bg-white p-8 text-center text-sm text-brand-green"
                >
                    <p class="font-semibold">
                        Nie znalazłyśmy ofert pasujących do tych filtrów.
                    </p>
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="mt-3 underline underline-offset-2"
                        @click="resetFilters"
                    >
                        Wyczyść filtry
                    </button>
                </div>

                <nav
                    v-if="offers.last_page > 1"
                    class="mt-8 flex items-center justify-between gap-3 text-sm text-brand-green"
                    aria-label="Paginacja"
                >
                    <Link
                        v-if="offers.prev_page_url"
                        :href="offers.prev_page_url"
                        class="inline-flex items-center gap-1 rounded-full border border-brand-green px-4 py-2 font-medium hover:bg-white"
                    >
                        <ChevronLeft class="size-4" /> Poprzednia
                    </Link>
                    <span v-else />
                    <span
                        >Strona {{ offers.current_page }} z
                        {{ offers.last_page }}</span
                    >
                    <Link
                        v-if="offers.next_page_url"
                        :href="offers.next_page_url"
                        class="inline-flex items-center gap-1 rounded-full border border-brand-green px-4 py-2 font-medium hover:bg-white"
                    >
                        Następna <ChevronRight class="size-4" />
                    </Link>
                    <span v-else />
                </nav>
            </div>
        </div>
    </div>
</template>
