<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Bell,
    Briefcase,
    CheckCircle2,
    Heart,
    Mail,
    MessageCircle,
    Plus,
    ShieldQuestion,
    Star,
    Users,
    UsersRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CandidateController from '@/actions/App/Http/Controllers/Employer/CandidateController';
import JobOfferController from '@/actions/App/Http/Controllers/Employer/JobOfferController';
import EmployerPairController from '@/actions/App/Http/Controllers/JobSharing/EmployerPairController';
import { pluralize } from '@/components/employer/format';
import { dashboard } from '@/routes/employer';

type FunnelRow = {
    offer_id: number;
    title: string;
    is_job_share: boolean;
    is_parent_friendly: boolean;
    matched_count: number;
    reviewed_count: number;
    to_review_count: number;
    invited_count: number;
    responded_count: number;
    accepted_count: number;
    submitted_pairs_count: number;
};

type TodoKind =
    | 'unread_messages'
    | 'submitted_pairs'
    | 'candidates_to_review'
    | 'offer_incomplete'
    | 'no_approved_reviews';

type TodoItem = {
    kind: TodoKind;
    count: number;
    offer_id: number | null;
    offer_title: string | null;
    names: string[];
    hints: string[];
    url: string;
};

type ActivityItem = {
    id: string;
    kind: string | null;
    title: string;
    body: string | null;
    url: string | null;
    read: boolean;
    created_at: string | null;
    created_at_diff: string | null;
    target: Record<string, number>;
};

const props = defineProps<{
    greeting: { first_name: string };
    company: {
        id: number;
        name: string;
        city: string | null;
        verified: boolean;
        verified_at: string | null;
    };
    stats: {
        published_offers_count: number;
        matching_candidates_count: number;
        to_review_count: number;
        invitations_sent_recent_count: number;
        recent_days: number;
        responded_count: number;
        accepted_count: number;
        acceptance_rate: number | null;
        active_conversations_count: number;
        submitted_pairs_count: number;
        parent_friendly: { count: number; total: number };
    };
    funnel: FunnelRow[];
    todo: TodoItem[];
    reviews: { approved_count: number; average_rating: number | null };
    activity: ActivityItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Start', href: dashboard() }],
    },
});

/**
 * Funnel stages share one ordinal green ramp (validated light -> dark), so later stages read darker.
 */
const stages = [
    { key: 'matched_count', label: 'Dopasowane', color: 'bg-[#80b5a8]' },
    { key: 'reviewed_count', label: 'Przejrzane', color: 'bg-[#4f8f82]' },
    { key: 'invited_count', label: 'Zaproszone', color: 'bg-[#2b6a61]' },
    { key: 'accepted_count', label: 'Przyjęte', color: 'bg-brand-green' },
] as const;

type StageKey = (typeof stages)[number]['key'];

const showFunnelTable = ref(false);

function rowMax(row: FunnelRow): number {
    return Math.max(1, ...stages.map((stage) => row[stage.key]));
}

function barWidth(row: FunnelRow, key: StageKey): string {
    const value = row[key];

    return value === 0 ? '0%' : `${Math.max(2, (value / rowMax(row)) * 100)}%`;
}

function stageShare(row: FunnelRow, key: StageKey): string | null {
    if (key === 'matched_count' || row.matched_count === 0) {
        return null;
    }

    return `${Math.round((row[key] / row.matched_count) * 100)}% dopasowanych`;
}

const jobShareOffers = computed(() =>
    props.funnel.filter((row) => row.is_job_share),
);

const parentFriendlyShare = computed(() =>
    props.stats.parent_friendly.total === 0
        ? 0
        : (props.stats.parent_friendly.count /
              props.stats.parent_friendly.total) *
          100,
);

const tiles = computed(() => [
    {
        label: 'Do przejrzenia',
        value: String(props.stats.to_review_count),
        hint: 'anonimowe profile w kolejce',
        icon: Users,
        highlight: props.stats.to_review_count > 0,
    },
    {
        label: 'Wysłane zaproszenia',
        value: String(props.stats.invitations_sent_recent_count),
        hint: `w ostatnich ${props.stats.recent_days} dniach`,
        icon: Mail,
    },
    {
        label: 'Akceptacja zaproszeń',
        value:
            props.stats.acceptance_rate === null
                ? '–'
                : `${props.stats.acceptance_rate}%`,
        hint:
            props.stats.responded_count === 0
                ? 'brak odpowiedzi'
                : `${props.stats.accepted_count} z ${props.stats.responded_count} odpowiedzi`,
        icon: CheckCircle2,
    },
    {
        label: 'Zgłoszone pary',
        value: String(props.stats.submitted_pairs_count),
        hint: 'job sharing czeka na decyzję',
        icon: UsersRound,
        highlight: props.stats.submitted_pairs_count > 0,
    },
    {
        label: 'Aktywne rozmowy',
        value: String(props.stats.active_conversations_count),
        hint: `wiadomości w ${props.stats.recent_days} dniach`,
        icon: MessageCircle,
    },
    {
        label: 'Opublikowane oferty',
        value: String(props.stats.published_offers_count),
        hint: 'stanowiska, do których zapraszasz',
        icon: Briefcase,
    },
]);

const hintLabels: Record<string, string> = {
    missing_required_skills: 'brak wymaganych umiejętności',
    missing_salary: 'brak widełek wynagrodzenia',
    no_flexible_hours: 'brak elastycznych godzin',
};

function todoTitle(item: TodoItem): string {
    switch (item.kind) {
        case 'unread_messages':
            return `${item.count} ${pluralize(item.count, 'rozmowa', 'rozmowy', 'rozmów')} z nieprzeczytanymi wiadomościami`;
        case 'submitted_pairs':
            return `${item.count} ${pluralize(item.count, 'para czeka', 'pary czekają', 'par czeka')} na decyzję`;
        case 'candidates_to_review':
            return `${item.count} ${pluralize(item.count, 'kandydatka czeka', 'kandydatki czekają', 'kandydatek czeka')} na przegląd`;
        case 'offer_incomplete':
            return 'Uzupełnij ofertę';
        case 'no_approved_reviews':
            return 'Zbierz pierwszą opinię o firmie';
    }

    return '';
}

function todoDescription(item: TodoItem): string {
    switch (item.kind) {
        case 'unread_messages':
            return item.names.join(', ');
        case 'offer_incomplete':
            return `${item.offer_title}: ${item.hints.map((hint) => hintLabels[hint] ?? hint).join(', ')}`;
        case 'no_approved_reviews':
            return 'Bez zatwierdzonej opinii żadna oferta nie dostanie odznaki „Przyjazna rodzicom”.';
        default:
            return item.offer_title ?? '';
    }
}

const todoIcons: Record<TodoKind, typeof Mail> = {
    unread_messages: MessageCircle,
    submitted_pairs: UsersRound,
    candidates_to_review: Users,
    offer_incomplete: Briefcase,
    no_approved_reviews: Star,
};

const activityIcons: Record<string, typeof Mail> = {
    invitation_accepted: CheckCircle2,
    invitation_declined: Mail,
    new_message: MessageCircle,
    pair_submitted: UsersRound,
    pair_accepted_company: UsersRound,
};
</script>

<template>
    <Head title="Start" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-8">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <h1
                    class="text-3xl leading-tight font-extrabold tracking-tight text-brand-green md:text-5xl"
                >
                    Dzień dobry, {{ greeting.first_name }}!
                </h1>
                <p
                    class="mt-2 flex flex-wrap items-center gap-2 text-brand-green/80"
                >
                    <span class="font-semibold text-brand-green">{{
                        company.name
                    }}</span>
                    <span
                        v-if="company.verified"
                        class="inline-flex items-center gap-1 rounded-full bg-brand-yellow px-3 py-1 text-xs font-semibold text-brand-green"
                        data-test="verified-badge"
                    >
                        <BadgeCheck class="size-3.5" aria-hidden="true" />
                        Zweryfikowany pracodawca
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full bg-brand-mint-soft px-3 py-1 text-xs font-semibold text-brand-green"
                    >
                        <ShieldQuestion class="size-3.5" aria-hidden="true" />
                        Weryfikacja NIP w toku
                    </span>
                </p>
            </div>
            <Link
                :href="JobOfferController.create()"
                class="inline-flex items-center gap-2 rounded-full border border-brand-green px-5 py-2.5 text-sm font-semibold text-brand-green transition hover:bg-white"
            >
                <Plus class="size-4" aria-hidden="true" /> Dodaj ofertę
            </Link>
        </header>

        <section
            class="flex flex-col gap-4 rounded-3xl bg-brand-green p-6 text-white md:flex-row md:items-end md:justify-between"
            aria-labelledby="hero-heading"
        >
            <div>
                <h2 id="hero-heading" class="text-sm text-white/80">
                    Kandydatki, które możesz zaprosić
                </h2>
                <p
                    class="mt-1 text-5xl font-extrabold md:text-6xl"
                    data-test="matching-candidates"
                >
                    {{ stats.matching_candidates_count }}
                </p>
                <p class="mt-1 max-w-md text-sm text-white/80">
                    <template v-if="stats.published_offers_count">
                        Anonimowe profile pasujące do
                        {{ stats.published_offers_count }}
                        {{
                            pluralize(
                                stats.published_offers_count,
                                'Twojego stanowiska',
                                'Twoich stanowisk',
                                'Twoich stanowisk',
                            )
                        }}. Dane kandydatki zobaczysz, gdy przyjmie zaproszenie.
                    </template>
                    <template v-else>
                        Opisz stanowisko (umiejętności, wymiar, start), a
                        pokażemy Ci kandydatki, które możesz zaprosić.
                    </template>
                </p>
            </div>
            <Link
                :href="
                    stats.published_offers_count
                        ? CandidateController.index()
                        : JobOfferController.create()
                "
                class="inline-flex items-center gap-2 self-start rounded-full bg-brand-yellow px-5 py-2.5 text-sm font-semibold text-brand-green transition hover:brightness-95 md:self-auto"
                data-test="invite-candidates-cta"
            >
                <template v-if="stats.published_offers_count">
                    Zaproś kandydatki ({{ stats.to_review_count }} do
                    przejrzenia)
                </template>
                <template v-else>Opisz pierwsze stanowisko</template>
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </section>

        <section
            class="flex flex-col gap-3 rounded-3xl bg-brand-mint-soft p-6"
            aria-labelledby="job-sharing-heading"
            data-test="job-sharing-section"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h2
                        id="job-sharing-heading"
                        class="flex items-center gap-2 text-lg font-bold text-brand-green"
                    >
                        <UsersRound class="size-5" aria-hidden="true" />
                        Job sharing
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm text-brand-green/80">
                        Kandydatki same dobierają się w pary, ustalają podział
                        dnia i razem pokrywają cały etat.
                    </p>
                </div>
                <span
                    v-if="stats.submitted_pairs_count"
                    class="shrink-0 rounded-full bg-brand-yellow px-3 py-1 text-xs font-semibold text-brand-green"
                >
                    {{ stats.submitted_pairs_count }}
                    {{
                        pluralize(
                            stats.submitted_pairs_count,
                            'para czeka',
                            'pary czekają',
                            'par czeka',
                        )
                    }}
                </span>
            </div>
            <ul v-if="jobShareOffers.length" class="flex flex-col gap-2">
                <li v-for="row in jobShareOffers" :key="row.offer_id">
                    <Link
                        :href="EmployerPairController.index(row.offer_id)"
                        class="flex items-center justify-between gap-3 rounded-2xl bg-white p-3 text-brand-green transition hover:shadow-sm"
                    >
                        <span class="min-w-0 truncate font-semibold">{{
                            row.title
                        }}</span>
                        <span
                            class="flex shrink-0 items-center gap-2 text-xs font-semibold"
                        >
                            {{ row.submitted_pairs_count }}
                            {{
                                pluralize(
                                    row.submitted_pairs_count,
                                    'zgłoszona para',
                                    'zgłoszone pary',
                                    'zgłoszonych par',
                                )
                            }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </span>
                    </Link>
                </li>
            </ul>
            <p
                v-else
                class="rounded-2xl bg-white/70 p-4 text-sm text-brand-green/80"
            >
                Zaznacz „Oferta dla wielu osób” przy stanowisku, a kandydatki
                zgłoszą się do niego w parach.
                <Link
                    :href="
                        JobOfferController.create({ query: { job_share: 1 } })
                    "
                    class="font-semibold underline underline-offset-4"
                    >Dodaj ofertę dla wielu osób</Link
                >
            </p>
        </section>

        <section aria-label="Najważniejsze liczby">
            <ul class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <li
                    v-for="tile in tiles"
                    :key="tile.label"
                    class="flex flex-col gap-1 rounded-3xl bg-white p-5 shadow-sm"
                >
                    <span
                        class="flex items-center gap-1.5 text-sm text-brand-green/80"
                    >
                        <component
                            :is="tile.icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ tile.label }}
                    </span>
                    <span class="text-3xl font-bold text-brand-green">
                        {{ tile.value }}
                        <span
                            v-if="tile.highlight"
                            class="ml-1 inline-block size-2 rounded-full bg-brand-peach align-middle"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="text-xs text-brand-green/80">{{
                        tile.hint
                    }}</span>
                </li>
                <li
                    class="col-span-2 flex flex-col gap-2 rounded-3xl bg-white p-5 shadow-sm"
                    data-test="parent-friendly-tile"
                >
                    <span
                        class="flex items-center gap-1.5 text-sm text-brand-green/80"
                    >
                        <Heart class="size-4 shrink-0" aria-hidden="true" />
                        Odznaka „Przyjazna rodzicom”
                    </span>
                    <span class="text-3xl font-bold text-brand-green">
                        {{ stats.parent_friendly.count }}
                        <span
                            class="text-base font-semibold text-brand-green/80"
                            >z {{ stats.parent_friendly.total }}
                            {{
                                pluralize(
                                    stats.parent_friendly.total,
                                    'oferty',
                                    'ofert',
                                    'ofert',
                                )
                            }}</span
                        >
                    </span>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-brand-mint-soft"
                        role="meter"
                        :aria-valuenow="stats.parent_friendly.count"
                        aria-valuemin="0"
                        :aria-valuemax="stats.parent_friendly.total"
                        aria-label="Oferty z odznaką Przyjazna rodzicom"
                    >
                        <div
                            class="h-full rounded-full bg-brand-green"
                            :style="{ width: `${parentFriendlyShare}%` }"
                        />
                    </div>
                    <span class="text-xs text-brand-green/80">
                        widełki płacowe + elastyczne godziny + zatwierdzona
                        opinia ({{ reviews.approved_count
                        }}{{
                            reviews.average_rating !== null
                                ? `, średnio ${reviews.average_rating}/5`
                                : ''
                        }})
                    </span>
                </li>
            </ul>
        </section>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
            <section
                class="rounded-3xl bg-white p-6 shadow-sm lg:col-span-3"
                aria-labelledby="funnel-heading"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2
                        id="funnel-heading"
                        class="text-lg font-bold text-brand-green"
                    >
                        Lejek zaproszeń
                    </h2>
                    <button
                        v-if="funnel.length"
                        type="button"
                        class="rounded-full bg-brand-mint-soft px-3 py-1 text-xs font-semibold text-brand-green transition hover:bg-brand-mint/40"
                        :aria-pressed="showFunnelTable"
                        @click="showFunnelTable = !showFunnelTable"
                    >
                        {{ showFunnelTable ? 'Pokaż wykres' : 'Pokaż tabelę' }}
                    </button>
                </div>
                <p class="mt-1 text-sm text-brand-green/80">
                    Dopasowane → przejrzane → zaproszone → przyjęte.
                </p>

                <p
                    v-if="!funnel.length"
                    class="mt-4 rounded-2xl bg-brand-cream p-4 text-sm text-brand-green/80"
                >
                    Nie masz jeszcze opublikowanych ofert.
                    <Link
                        :href="JobOfferController.create()"
                        class="font-semibold underline underline-offset-4"
                        >Dodaj pierwszą</Link
                    >, a pokażemy tu pasujące kandydatki.
                </p>

                <div
                    v-else-if="showFunnelTable"
                    class="mt-4 overflow-x-auto"
                    data-test="funnel-table"
                >
                    <table class="w-full text-left text-sm text-brand-green">
                        <thead>
                            <tr class="border-b border-brand-mint-soft">
                                <th scope="col" class="py-2 pr-3 font-semibold">
                                    Oferta
                                </th>
                                <th
                                    v-for="stage in stages"
                                    :key="stage.key"
                                    scope="col"
                                    class="px-2 py-2 text-right font-semibold"
                                >
                                    {{ stage.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in funnel"
                                :key="row.offer_id"
                                class="border-b border-brand-mint-soft/60 last:border-0"
                            >
                                <th scope="row" class="py-2 pr-3 font-medium">
                                    {{ row.title }}
                                </th>
                                <td
                                    v-for="stage in stages"
                                    :key="stage.key"
                                    class="px-2 py-2 text-right tabular-nums"
                                >
                                    {{ row[stage.key] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul v-else class="mt-4 flex flex-col gap-5">
                    <li
                        v-for="row in funnel"
                        :key="row.offer_id"
                        data-test="funnel-row"
                    >
                        <div
                            class="mb-2 flex flex-wrap items-center justify-between gap-2"
                        >
                            <Link
                                :href="
                                    CandidateController.index({
                                        query: { offer: row.offer_id },
                                    })
                                "
                                class="min-w-0 truncate font-semibold text-brand-green hover:underline"
                            >
                                {{ row.title }}
                            </Link>
                            <span class="flex flex-wrap gap-1.5">
                                <span
                                    v-if="row.is_parent_friendly"
                                    class="rounded-full bg-brand-yellow px-2.5 py-0.5 text-xs font-semibold text-brand-green"
                                    >Przyjazna rodzicom</span
                                >
                                <span
                                    v-if="row.to_review_count"
                                    class="rounded-full bg-brand-mint-soft px-2.5 py-0.5 text-xs font-semibold text-brand-green"
                                    >{{ row.to_review_count }} do
                                    przejrzenia</span
                                >
                            </span>
                        </div>
                        <dl class="flex flex-col gap-0.5">
                            <div
                                v-for="stage in stages"
                                :key="stage.key"
                                class="group relative grid grid-cols-[6.5rem_1fr] items-center gap-2 py-px"
                                tabindex="0"
                                :aria-label="`${stage.label}: ${row[stage.key]}${stageShare(row, stage.key) ? `, ${stageShare(row, stage.key)}` : ''}`"
                            >
                                <dt class="text-xs text-brand-green/80">
                                    {{ stage.label }}
                                </dt>
                                <dd class="flex items-center gap-2">
                                    <span
                                        class="h-3.5 rounded-r-[4px] transition group-hover:brightness-110 group-focus:brightness-110"
                                        :class="stage.color"
                                        :style="{
                                            width: barWidth(row, stage.key),
                                        }"
                                    />
                                    <span
                                        class="text-xs font-semibold text-brand-green"
                                        >{{ row[stage.key] }}</span
                                    >
                                    <span
                                        v-if="stageShare(row, stage.key)"
                                        class="pointer-events-none absolute -top-6 right-0 z-10 hidden rounded-lg bg-brand-green px-2 py-1 text-xs whitespace-nowrap text-white shadow group-hover:block group-focus:block"
                                        role="tooltip"
                                    >
                                        <strong>{{ row[stage.key] }}</strong>
                                        {{ stage.label.toLowerCase() }} ·
                                        {{ stageShare(row, stage.key) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </li>
                </ul>
            </section>

            <section
                class="rounded-3xl bg-white p-6 shadow-sm lg:col-span-2"
                aria-labelledby="todo-heading"
            >
                <h2
                    id="todo-heading"
                    class="text-lg font-bold text-brand-green"
                >
                    Do zrobienia
                </h2>
                <p
                    v-if="!todo.length"
                    class="mt-3 rounded-2xl bg-brand-cream p-4 text-sm text-brand-green/80"
                >
                    Na razie nie masz nic do zrobienia.
                </p>
                <ul v-else class="mt-3 flex flex-col gap-2" data-test="todo">
                    <li
                        v-for="(item, position) in todo"
                        :key="`${item.kind}-${item.offer_id ?? position}`"
                    >
                        <Link
                            :href="item.url"
                            class="flex items-start gap-3 rounded-2xl bg-brand-cream p-3 transition hover:bg-brand-mint-soft"
                        >
                            <span
                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-white text-brand-green"
                            >
                                <component
                                    :is="todoIcons[item.kind]"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-semibold text-brand-green"
                                    >{{ todoTitle(item) }}</span
                                >
                                <span
                                    v-if="todoDescription(item)"
                                    class="block text-xs text-brand-green/80"
                                    >{{ todoDescription(item) }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>

        <section
            class="rounded-3xl bg-white p-6 shadow-sm"
            aria-labelledby="activity-heading"
        >
            <h2
                id="activity-heading"
                class="flex items-center gap-2 text-lg font-bold text-brand-green"
            >
                <Bell class="size-5" aria-hidden="true" /> Ostatnia aktywność
            </h2>
            <p v-if="!activity.length" class="mt-3 text-sm text-brand-green/80">
                Tu zobaczysz odpowiedzi na zaproszenia, nowe wiadomości i
                zgłoszenia par.
            </p>
            <ol
                v-else
                class="mt-3 divide-y divide-brand-mint-soft"
                data-test="activity"
            >
                <li v-for="item in activity" :key="item.id">
                    <component
                        :is="item.url ? Link : 'div'"
                        :href="item.url ?? undefined"
                        class="flex items-start gap-3 py-3"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                            :class="
                                item.read
                                    ? 'bg-brand-cream text-brand-green/80'
                                    : 'bg-brand-yellow text-brand-green'
                            "
                        >
                            <component
                                :is="activityIcons[item.kind ?? ''] ?? Bell"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block text-sm text-brand-green"
                                :class="{ 'font-semibold': !item.read }"
                                >{{ item.title }}</span
                            >
                            <span
                                v-if="item.body"
                                class="block truncate text-xs text-brand-green/80"
                                >{{ item.body }}</span
                            >
                        </span>
                        <time
                            v-if="item.created_at"
                            :datetime="item.created_at"
                            class="shrink-0 text-xs text-brand-green/80"
                            >{{ item.created_at_diff }}</time
                        >
                    </component>
                </li>
            </ol>
        </section>
    </div>
</template>
