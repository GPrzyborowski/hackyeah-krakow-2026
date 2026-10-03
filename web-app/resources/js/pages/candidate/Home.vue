<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Bookmark,
    Lock,
    Mail,
    Sparkles,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import PairController from '@/actions/App/Http/Controllers/JobSharing/PairController';
import { pluralize } from '@/components/candidate/format';
import MatchPill from '@/components/candidate/MatchPill.vue';
import ReturnCalendarCard from '@/components/candidate/profile/ReturnCalendarCard.vue';
import type { ReturnCalendar } from '@/components/candidate/types';
import { index as assistantIndex } from '@/routes/assistant';
import { show as blogShow } from '@/routes/blog';
import { cvAnalysis, home, profile as profileRoute } from '@/routes/candidate';
import { index as invitationsIndex } from '@/routes/candidate/invitations';
import {
    index as offersIndex,
    show as offerShow,
} from '@/routes/candidate/offers';
import { show as onboarding } from '@/routes/candidate/onboarding';

const props = defineProps<{
    firstName: string;
    stageMessage: string | null;
    calendar: ReturnCalendar;
    invitations: { pending_count: number; company_names: string[] };
    pairInvitationsCount?: number;
    savedOffersCount: number;
    topOffers: {
        id: number;
        title: string;
        company: string;
        work_mode_label: string;
        city: string | null;
        score: number;
    }[];
    recommendedArticles: {
        id: number;
        title: string;
        slug: string;
        excerpt: string;
        category_label: string;
        reading_minutes: number;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Start', href: home() }],
    },
});

const numberWords: Record<number, string> = {
    2: 'Dwie',
    3: 'Trzy',
    4: 'Cztery',
};

const subtitle = computed(() => {
    const count = props.invitations.company_names.length;

    if (count === 0) {
        return 'Twój profil jest widoczny dla firm.';
    }

    if (count === 1) {
        return 'Jedna firma już Cię zauważyła.';
    }

    const amount = numberWords[count] ?? String(count);

    return `${amount} ${pluralize(count, 'firma', 'firmy', 'firm')} już Cię ${pluralize(count, 'zauważyła', 'zauważyły', 'zauważyło')}.`;
});

const hasPrivateDates = computed(
    () =>
        props.calendar.due_date !== null ||
        props.calendar.leave_starts_on !== null,
);

const calendarEditLabel = computed(() => {
    if (hasPrivateDates.value) {
        return 'Zmień daty';
    }

    return props.calendar.stage === 'after_leave'
        ? 'Dodaj prywatne daty (urlop, powrót)'
        : 'Dodaj prywatne daty (termin porodu, start urlopu)';
});

function editCalendar() {
    router.visit(onboarding({ query: { step: 3 } }));
}
</script>

<template>
    <Head title="Start" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 md:p-8">
        <h1
            class="text-3xl leading-tight font-extrabold tracking-tight text-brand-green md:text-5xl"
        >
            Cześć, {{ firstName }}. {{ subtitle }}
        </h1>

        <p
            v-if="stageMessage"
            class="-mt-2 text-brand-green/80 md:text-lg"
            data-test="stage-message"
        >
            {{ stageMessage }}
        </p>

        <Link
            v-if="calendar.stage === null"
            :href="profileRoute()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-brand-yellow p-6 text-brand-green transition hover:brightness-95"
            data-test="stage-prompt-card"
        >
            <div class="min-w-0">
                <h2 class="text-lg font-bold">Gdzie teraz jesteś?</h2>
                <p class="text-sm">
                    Powiedz nam, czy jesteś w ciąży, czy po urlopie
                    macierzyńskim, a dopasujemy kalendarz i artykuły.
                </p>
                <p class="mt-1 inline-flex items-center gap-1 text-xs">
                    <Lock class="size-3" aria-hidden="true" />
                    Tę informację widzisz tylko Ty. Nie pokazujemy jej
                    pracodawcom.
                </p>
            </div>
            <ArrowRight class="size-5 shrink-0" aria-hidden="true" />
        </Link>

        <Link
            :href="invitationsIndex()"
            class="flex flex-col gap-4 rounded-3xl bg-brand-green p-6 text-white transition hover:bg-brand-green-soft sm:flex-row sm:items-center sm:justify-between"
            data-test="invitations-card"
        >
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-xl font-bold">
                    <Mail
                        class="size-5 shrink-0 text-brand-yellow"
                        aria-hidden="true"
                    />
                    Zaproszenia od firm
                </h2>
                <p class="mt-1 text-white/85">
                    {{
                        invitations.company_names.length
                            ? invitations.company_names.join(', ')
                            : 'Firmy przeglądają anonimowe profile i same zapraszają do rozmowy. Twoje dane zobaczą dopiero po Twojej zgodzie.'
                    }}
                </p>
            </div>
            <span
                class="shrink-0 self-start rounded-full px-4 py-1.5 text-sm font-semibold text-brand-green sm:self-auto"
                :class="
                    invitations.pending_count
                        ? 'bg-brand-yellow'
                        : 'bg-brand-mint-soft'
                "
            >
                {{ invitations.pending_count }}
                {{
                    pluralize(
                        invitations.pending_count,
                        'nowe',
                        'nowe',
                        'nowych',
                    )
                }}
            </span>
        </Link>

        <Link
            :href="PairController.index()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-brand-mint-soft p-6 transition hover:shadow-md"
            data-test="job-sharing-card"
        >
            <div class="min-w-0">
                <h2
                    class="flex items-center gap-2 text-lg font-bold text-brand-green"
                >
                    <UsersRound class="size-5 shrink-0" aria-hidden="true" />
                    {{
                        pairInvitationsCount
                            ? 'Zaproszenia do pary'
                            : 'Job sharing'
                    }}
                </h2>
                <p class="text-brand-green/80">
                    {{
                        pairInvitationsCount
                            ? 'Ktoś chce dzielić z Tobą stanowisko w job sharingu.'
                            : 'Podziel etat z inną mamą: jedna pracuje rano, druga po południu.'
                    }}
                </p>
            </div>
            <span
                v-if="pairInvitationsCount"
                class="shrink-0 rounded-full bg-brand-yellow px-4 py-1.5 text-sm font-semibold text-brand-green"
                data-test="pair-invitations-count"
            >
                {{ pairInvitationsCount }}
                {{ pluralize(pairInvitationsCount, 'nowe', 'nowe', 'nowych') }}
            </span>
            <ArrowRight
                v-else
                class="size-5 shrink-0 text-brand-green"
                aria-hidden="true"
            />
        </Link>

        <ReturnCalendarCard
            :calendar="calendar"
            :edit-label="calendarEditLabel"
            @edit="editCalendar"
        />

        <Link
            :href="cvAnalysis()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-brand-green p-6 text-white transition hover:bg-brand-green-soft"
            data-test="cv-analysis-card"
        >
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-lg font-bold">
                    <Sparkles
                        class="size-5 shrink-0 text-brand-yellow"
                        aria-hidden="true"
                    />
                    Analiza CV
                </h2>
                <p class="text-sm text-white/85">
                    Zobacz swoje mocne strony i czego brakuje do lepszego
                    dopasowania.
                </p>
            </div>
            <span
                class="shrink-0 rounded-full bg-brand-yellow px-4 py-1.5 text-sm font-semibold text-brand-green"
            >
                Zobacz analizę CV
            </span>
        </Link>

        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-brand-green">
                    Możesz też sama rozejrzeć się po ofertach
                </h2>
                <Link
                    :href="offersIndex()"
                    class="shrink-0 text-sm font-semibold text-brand-green underline underline-offset-4"
                >
                    Zobacz wszystkie
                </Link>
            </div>
            <Link
                v-if="savedOffersCount"
                :href="offersIndex({ query: { saved: 1 } })"
                class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-brand-green hover:underline"
                data-test="saved-offers-card"
            >
                <Bookmark class="size-4" aria-hidden="true" />
                Zapisane oferty ({{ savedOffersCount }})
            </Link>
            <ul class="mt-3 divide-y divide-brand-cream">
                <li v-for="offer in topOffers" :key="offer.id">
                    <Link
                        :href="offerShow(offer.id)"
                        class="flex items-center justify-between gap-3 py-3 text-brand-green hover:underline"
                    >
                        <span class="min-w-0">
                            <span class="block truncate"
                                >{{ offer.title }} ·
                                {{ offer.work_mode_label.toLowerCase() }}</span
                            >
                            <span class="block text-xs text-brand-green/80">{{
                                offer.company
                            }}</span>
                        </span>
                        <MatchPill :score="offer.score" />
                    </Link>
                </li>
                <li
                    v-if="topOffers.length === 0"
                    class="py-3 text-sm text-brand-green/80"
                >
                    Jeszcze nie ma opublikowanych ofert.
                </li>
            </ul>
        </section>

        <section
            v-if="recommendedArticles.length"
            class="rounded-3xl bg-white p-6 shadow-sm"
            aria-labelledby="recommended-articles-heading"
            data-test="recommended-articles"
        >
            <h2
                id="recommended-articles-heading"
                class="flex items-center gap-2 text-lg font-bold text-brand-green"
            >
                <BookOpen class="size-5" aria-hidden="true" />
                Poczytaj dla siebie
            </h2>
            <ul class="mt-3 divide-y divide-brand-cream">
                <li v-for="article in recommendedArticles" :key="article.id">
                    <Link
                        :href="blogShow(article.slug)"
                        class="block py-3 text-brand-green hover:underline"
                    >
                        <span
                            class="block text-xs font-semibold text-brand-green/80"
                            >{{ article.category_label }} ·
                            {{ article.reading_minutes }} min</span
                        >
                        <span class="block font-semibold">{{
                            article.title
                        }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <Link
            :href="assistantIndex()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-brand-peach p-6 text-brand-green transition hover:brightness-95"
        >
            <div>
                <h2 class="text-lg font-bold">Masz pytanie o swoje prawa?</h2>
                <p class="text-sm">
                    Zapytaj asystenta. Do każdej odpowiedzi poda źródło.
                </p>
            </div>
            <ArrowRight class="size-5 shrink-0" />
        </Link>
    </div>
</template>
