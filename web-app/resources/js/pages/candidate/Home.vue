<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Lock } from '@lucide/vue';
import { computed } from 'vue';
import PairController from '@/actions/App/Http/Controllers/JobSharing/PairController';
import { formatShortDate, pluralize } from '@/components/candidate/format';
import MatchPill from '@/components/candidate/MatchPill.vue';
import { index as assistantIndex } from '@/routes/assistant';
import { home } from '@/routes/candidate';
import { index as invitationsIndex } from '@/routes/candidate/invitations';
import {
    index as offersIndex,
    show as offerShow,
} from '@/routes/candidate/offers';
import { show as onboarding } from '@/routes/candidate/onboarding';

type Phase = 'pregnancy' | 'leave' | 'ready';

const props = defineProps<{
    firstName: string;
    calendar: {
        pregnancy_week: number | null;
        due_date: string | null;
        leave_starts_on: string | null;
        available_from: string | null;
        current_phase: Phase;
    };
    invitations: { pending_count: number; company_names: string[] };
    pairInvitationsCount?: number;
    topOffers: {
        id: number;
        title: string;
        company: string;
        work_mode_label: string;
        city: string | null;
        score: number;
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

const phases = computed(() => {
    const { calendar } = props;

    return [
        {
            key: 'pregnancy' as Phase,
            bar: 'bg-brand-mint',
            label: calendar.pregnancy_week
                ? `${calendar.pregnancy_week}. tydzień`
                : 'ciąża',
        },
        {
            key: 'leave' as Phase,
            bar: 'bg-brand-peach',
            label: calendar.leave_starts_on
                ? `urlop od ${formatShortDate(calendar.leave_starts_on)}`
                : 'urlop',
        },
        {
            key: 'ready' as Phase,
            bar: 'bg-brand-yellow',
            label: calendar.available_from
                ? `gotowa ${formatShortDate(calendar.available_from)}`
                : 'gotowa',
        },
    ];
});

const hasPrivateDates = computed(
    () =>
        props.calendar.due_date !== null ||
        props.calendar.leave_starts_on !== null,
);
</script>

<template>
    <Head title="Start" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 md:p-8">
        <h1
            class="text-3xl leading-tight font-extrabold tracking-tight text-brand-green md:text-5xl"
        >
            Cześć, {{ firstName }}. {{ subtitle }}
        </h1>

        <section class="rounded-3xl bg-brand-green p-6 text-white">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold">Twój kalendarz powrotu</h2>
                <span
                    class="inline-flex items-center gap-1 text-xs text-white/60"
                >
                    <Lock class="size-3" /> widzisz tylko Ty
                </span>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-1.5">
                <div
                    v-for="phase in phases"
                    :key="phase.key"
                    class="h-3 rounded-full"
                    :class="[
                        phase.bar,
                        calendar.current_phase === phase.key
                            ? 'ring-2 ring-white ring-offset-2 ring-offset-brand-green'
                            : 'opacity-80',
                    ]"
                />
            </div>
            <div
                class="mt-3 grid grid-cols-3 gap-1.5 text-xs text-white/80 sm:text-sm"
            >
                <span
                    v-for="(phase, position) in phases"
                    :key="phase.key"
                    :class="{
                        'text-center': position === 1,
                        'text-right': position === 2,
                        'font-semibold text-white':
                            calendar.current_phase === phase.key,
                    }"
                >
                    {{ phase.label }}
                </span>
            </div>
            <Link
                v-if="!hasPrivateDates"
                :href="onboarding({ query: { step: 3 } })"
                class="mt-4 inline-block text-xs text-white/70 underline underline-offset-4"
            >
                Dodaj prywatne daty (termin porodu, start urlopu)
            </Link>
        </section>

        <Link
            :href="invitationsIndex()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-white p-6 shadow-sm transition hover:shadow-md"
        >
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-brand-green">
                    Zaproszenia od firm
                </h2>
                <p class="truncate text-brand-green/70">
                    {{
                        invitations.company_names.length
                            ? invitations.company_names.join(', ')
                            : 'Na razie brak nowych zaproszeń.'
                    }}
                </p>
            </div>
            <span
                class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold text-brand-green"
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
            v-if="pairInvitationsCount"
            :href="PairController.index()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-brand-mint-soft p-6 transition hover:shadow-md"
            data-test="pair-invitations-card"
        >
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-brand-green">
                    Zaproszenia do pary
                </h2>
                <p class="text-brand-green/70">
                    Ktoś chce dzielić z Tobą stanowisko w job sharingu.
                </p>
            </div>
            <span
                class="shrink-0 rounded-full bg-brand-yellow px-4 py-1.5 text-sm font-semibold text-brand-green"
            >
                {{ pairInvitationsCount }}
                {{ pluralize(pairInvitationsCount, 'nowe', 'nowe', 'nowych') }}
            </span>
        </Link>

        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-brand-green">
                    Pasujące oferty
                </h2>
                <Link
                    :href="offersIndex()"
                    class="text-sm font-semibold text-brand-green underline underline-offset-4"
                >
                    Zobacz wszystkie
                </Link>
            </div>
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
                            <span class="block text-xs text-brand-green/60">{{
                                offer.company
                            }}</span>
                        </span>
                        <MatchPill :score="offer.score" />
                    </Link>
                </li>
                <li
                    v-if="topOffers.length === 0"
                    class="py-3 text-sm text-brand-green/70"
                >
                    Jeszcze nie ma opublikowanych ofert.
                </li>
            </ul>
        </section>

        <Link
            :href="assistantIndex()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-brand-peach p-6 text-brand-green transition hover:brightness-95"
        >
            <div>
                <h2 class="text-lg font-bold">Masz pytanie o swoje prawa?</h2>
                <p class="text-sm">Zapytaj asystenta. Odpowiada ze źródłem.</p>
            </div>
            <ArrowRight class="size-5 shrink-0" />
        </Link>
    </div>
</template>
