<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BriefcaseBusiness,
    Check,
    FileText,
    Lock,
    Plus,
    Sparkles,
    TrendingUp,
} from '@lucide/vue';
import { formatShortDate, pluralize } from '@/components/candidate/format';
import MatchPill from '@/components/candidate/MatchPill.vue';
import { cvAnalysis } from '@/routes/candidate';
import {
    index as offersIndex,
    show as offerShow,
} from '@/routes/candidate/offers';
import { show as onboarding } from '@/routes/candidate/onboarding';
import { store as storeSkill } from '@/routes/candidate/skills';

type Position = { title: string; score: number };

defineProps<{
    analysis: {
        stats: {
            offers_total: number;
            matching_offers: number;
            average_score: number;
            can_start_on_time: number;
            good_match_threshold: number;
        };
        strengths: { id: number; name: string; demand_count: number }[];
        positions: { suggested: Position[]; offers: Position[] };
        missing_skills: {
            id: number;
            name: string;
            offers_count: number;
            average_gain: number;
        }[];
        offers: {
            id: number;
            title: string;
            company: string;
            city: string | null;
            work_mode_label: string;
            employment_fraction_label: string;
            start_date: string;
            score: number;
            matched_skills: string[];
            missing_required: string[];
            missing_nice_to_have: string[];
            start_date_compatible: boolean;
        }[];
    };
    cvFileName: string | null;
    headline: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Oferty', href: offersIndex() },
            { title: 'Analiza CV', href: cvAnalysis() },
        ],
    },
});
</script>

<template>
    <Head title="Analiza CV" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <section
            class="rounded-3xl bg-brand-green p-6 text-white md:p-8"
            aria-labelledby="cv-analysis-title"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1
                        id="cv-analysis-title"
                        class="flex items-center gap-2 text-2xl leading-tight font-extrabold tracking-tight md:text-4xl"
                    >
                        <Sparkles
                            class="size-6 shrink-0 text-brand-yellow md:size-8"
                            aria-hidden="true"
                        />
                        Dopasowane do Twojego CV
                    </h1>
                    <p class="mt-2 max-w-2xl text-white/85">
                        Asystent AI porównał Twoje umiejętności z ofertami i
                        ułożył je od najlepiej pasujących.
                    </p>
                </div>
                <span
                    class="inline-flex items-center gap-1 text-xs text-white/85"
                >
                    <Lock class="size-3" aria-hidden="true" /> widzisz tylko Ty
                </span>
            </div>

            <p
                v-if="cvFileName || headline"
                class="mt-4 inline-flex max-w-full items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm"
            >
                <FileText class="size-4 shrink-0" aria-hidden="true" />
                <span class="truncate">{{ cvFileName ?? headline }}</span>
            </p>

            <dl class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-white/10 p-4">
                    <dt class="text-sm text-white/85">
                        Oferty z dopasowaniem od
                        {{ analysis.stats.good_match_threshold }}%
                    </dt>
                    <dd class="mt-1 text-3xl font-extrabold text-brand-yellow">
                        {{ analysis.stats.matching_offers }}
                        <span class="text-base font-semibold text-white/85"
                            >z {{ analysis.stats.offers_total }}</span
                        >
                    </dd>
                </div>
                <div class="rounded-2xl bg-white/10 p-4">
                    <dt class="text-sm text-white/85">Średnie dopasowanie</dt>
                    <dd class="mt-1 text-3xl font-extrabold text-brand-yellow">
                        {{ analysis.stats.average_score }}%
                    </dd>
                </div>
                <div class="rounded-2xl bg-white/10 p-4">
                    <dt class="text-sm text-white/85">
                        Oferty, do których zdążysz
                    </dt>
                    <dd class="mt-1 text-3xl font-extrabold text-brand-yellow">
                        {{ analysis.stats.can_start_on_time }}
                    </dd>
                </div>
            </dl>
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-3xl bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-brand-green">
                    Twoje mocne strony
                </h2>
                <p class="mt-1 text-sm text-brand-green/80">
                    Umiejętności, o które firmy pytają najczęściej.
                </p>
                <ul
                    v-if="analysis.strengths.length"
                    class="mt-4 flex flex-col gap-2"
                >
                    <li
                        v-for="skill in analysis.strengths"
                        :key="skill.id"
                        class="flex items-center justify-between gap-3 rounded-2xl bg-brand-cream px-4 py-2.5 text-brand-green"
                    >
                        <span class="inline-flex min-w-0 items-center gap-2">
                            <Check class="size-4 shrink-0" aria-hidden="true" />
                            <span class="truncate font-medium">{{
                                skill.name
                            }}</span>
                        </span>
                        <span class="shrink-0 text-xs font-semibold">
                            {{ skill.demand_count }}
                            {{
                                pluralize(
                                    skill.demand_count,
                                    'oferta',
                                    'oferty',
                                    'ofert',
                                )
                            }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-brand-green/80">
                    Nie masz jeszcze zatwierdzonych umiejętności.
                    <Link
                        :href="onboarding({ query: { step: 2 } })"
                        class="font-semibold underline underline-offset-4"
                        >Dodaj je w profilu</Link
                    >.
                </p>
            </section>

            <section class="rounded-3xl bg-white p-6 shadow-sm">
                <h2
                    class="flex items-center gap-2 text-lg font-bold text-brand-green"
                >
                    <BriefcaseBusiness class="size-5" aria-hidden="true" />
                    Stanowiska, które do Ciebie pasują
                </h2>
                <template v-if="analysis.positions.suggested.length">
                    <h3
                        class="mt-4 text-xs font-semibold tracking-wide text-brand-green/80 uppercase"
                    >
                        Na podstawie CV
                    </h3>
                    <ul class="mt-2 divide-y divide-brand-cream">
                        <li
                            v-for="position in analysis.positions.suggested"
                            :key="position.title"
                            class="flex items-center justify-between gap-3 py-2 text-brand-green"
                        >
                            <span class="min-w-0 truncate">{{
                                position.title
                            }}</span>
                            <MatchPill :score="position.score" />
                        </li>
                    </ul>
                </template>
                <template v-if="analysis.positions.offers.length">
                    <h3
                        class="mt-4 text-xs font-semibold tracking-wide text-brand-green/80 uppercase"
                    >
                        Najlepiej pasujące oferty
                    </h3>
                    <ul class="mt-2 divide-y divide-brand-cream">
                        <li
                            v-for="position in analysis.positions.offers"
                            :key="position.title"
                            class="flex items-center justify-between gap-3 py-2 text-brand-green"
                        >
                            <span class="min-w-0 truncate">{{
                                position.title
                            }}</span>
                            <MatchPill :score="position.score" />
                        </li>
                    </ul>
                </template>
                <p
                    v-if="
                        !analysis.positions.suggested.length &&
                        !analysis.positions.offers.length
                    "
                    class="mt-4 text-sm text-brand-green/80"
                >
                    Dodaj CV, a asystent AI zaproponuje stanowiska.
                </p>
            </section>
        </div>

        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <h2
                class="flex items-center gap-2 text-lg font-bold text-brand-green"
            >
                <TrendingUp class="size-5" aria-hidden="true" />
                Czego brakuje do lepszego dopasowania
            </h2>
            <p class="mt-1 text-sm text-brand-green/80">
                Wymagane umiejętności z ofert, do których pasujesz już co
                najmniej w połowie. Jeśli je masz, dodaj je jednym kliknięciem.
            </p>
            <ul
                v-if="analysis.missing_skills.length"
                class="mt-4 flex flex-col gap-3"
            >
                <li
                    v-for="skill in analysis.missing_skills"
                    :key="skill.id"
                    class="flex flex-col gap-3 rounded-2xl border border-brand-mint-soft p-4 sm:flex-row sm:items-center"
                    data-test="missing-skill"
                >
                    <div class="min-w-0 flex-1 text-brand-green">
                        <p class="font-semibold">{{ skill.name }}</p>
                        <p class="text-sm text-brand-green/80">
                            Poprawi dopasowanie w {{ skill.offers_count }}
                            {{
                                pluralize(
                                    skill.offers_count,
                                    'ofercie',
                                    'ofertach',
                                    'ofertach',
                                )
                            }}
                        </p>
                    </div>
                    <span
                        class="self-start rounded-full bg-brand-yellow px-3 py-1 text-xs font-semibold text-brand-green sm:self-auto"
                    >
                        średnio +{{ skill.average_gain }} pkt %
                    </span>
                    <Form
                        v-bind="storeSkill.form()"
                        :options="{ preserveScroll: true }"
                        #default="{ processing }"
                    >
                        <input type="hidden" name="name" :value="skill.name" />
                        <button
                            type="submit"
                            :disabled="processing"
                            class="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-4 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-60"
                            :aria-label="`Mam tę umiejętność: ${skill.name}`"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Mam tę umiejętność
                        </button>
                    </Form>
                </li>
            </ul>
            <p v-else class="mt-4 text-sm text-brand-green/80">
                W ofertach, do których pasujesz, masz już wszystkie wymagane
                umiejętności.
            </p>
        </section>

        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-brand-green">
                    Oferty dopasowane do Twojego CV
                </h2>
                <Link
                    :href="offersIndex()"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-brand-green underline underline-offset-4"
                >
                    Wszystkie oferty
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <ol
                v-if="analysis.offers.length"
                class="mt-3 divide-y divide-brand-cream"
            >
                <li
                    v-for="offer in analysis.offers"
                    :key="offer.id"
                    class="py-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link
                                :href="offerShow(offer.id)"
                                class="font-semibold text-brand-green hover:underline"
                            >
                                {{ offer.title }}
                            </Link>
                            <p class="text-sm text-brand-green/80">
                                {{ offer.company }}
                                <template v-if="offer.city"
                                    >· {{ offer.city }}</template
                                >
                                · {{ offer.work_mode_label.toLowerCase() }} ·
                                start
                                {{ formatShortDate(offer.start_date, true) }}
                            </p>
                        </div>
                        <MatchPill :score="offer.score" />
                    </div>
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        <li
                            v-for="name in offer.matched_skills"
                            :key="`m-${name}`"
                            class="inline-flex items-center gap-1 rounded-full bg-brand-mint-soft px-3 py-1 text-xs font-medium text-brand-green"
                        >
                            <Check class="size-3" aria-hidden="true" />
                            {{ name }}
                        </li>
                        <li
                            v-for="name in offer.missing_required"
                            :key="`r-${name}`"
                            class="inline-flex items-center rounded-full border border-brand-green/30 px-3 py-1 text-xs font-medium text-brand-green"
                        >
                            <span class="sr-only">Brakuje:&nbsp;</span>
                            {{ name }}
                        </li>
                        <li
                            v-for="name in offer.missing_nice_to_have"
                            :key="`n-${name}`"
                            class="inline-flex items-center rounded-full border border-dashed border-brand-green/30 px-3 py-1 text-xs text-brand-green/80"
                        >
                            <span class="sr-only">Mile widziane:&nbsp;</span>
                            {{ name }}
                        </li>
                    </ul>
                    <p
                        v-if="!offer.start_date_compatible"
                        class="mt-2 text-xs text-brand-green/80"
                    >
                        Start wcześniej niż Twoja data dostępności.
                    </p>
                </li>
            </ol>
            <p v-else class="mt-3 text-sm text-brand-green/80">
                Jeszcze nie ma opublikowanych ofert.
            </p>
            <p
                v-if="analysis.offers.length"
                class="mt-2 text-xs text-brand-green/80"
            >
                Zielone tagi masz w profilu, obramowane to wymagane braki,
                przerywane – mile widziane.
            </p>
        </section>

        <p
            class="flex items-start gap-2 rounded-3xl bg-brand-mint-soft p-4 text-sm text-brand-green"
        >
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Analiza jest widoczna tylko dla Ciebie. Firmy nie widzą Twojego CV,
            braków ani wyników. Widzą zatwierdzone umiejętności i procent
            dopasowania do ich oferty.
        </p>
    </div>
</template>
