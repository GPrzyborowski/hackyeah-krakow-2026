<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, MessageSquare, UsersRound, X } from '@lucide/vue';
import { ref } from 'vue';
import EmployerPairController from '@/actions/App/Http/Controllers/JobSharing/EmployerPairController';
import CandidateController from '@/actions/App/Http/Controllers/Employer/CandidateController';
import JobOfferController from '@/actions/App/Http/Controllers/Employer/JobOfferController';
import { formatShortDate } from '@/components/employer/format';
import type { AnonymousCandidate } from '@/components/employer/types';
import { formatHour } from '@/components/job-sharing/format';
import PairInviteDialog from '@/components/job-sharing/PairInviteDialog.vue';
import ScheduleBar from '@/components/job-sharing/ScheduleBar.vue';
import type {
    PairStatus,
    ScheduleBarBlock,
    ScheduleBlock,
} from '@/components/job-sharing/types';

type Offer = {
    id: number;
    title: string;
    city: string | null;
    start_date: string;
    salary_min: number | null;
    salary_max: number | null;
    employment_fraction_label: string;
    work_mode_label: string;
    flexible_hours: boolean;
    fixed_meeting_hours: boolean;
    workday_starts_at: string;
    workday_ends_at: string;
};

type Pair = {
    id: number;
    status: PairStatus;
    submitted_at: string | null;
    members: AnonymousCandidate[];
    coverage: { covered: string[]; missing: string[]; percent: number };
    schedule: ScheduleBlock[];
};

const props = defineProps<{
    offer: Offer;
    pairs: Pair[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ogłoszenia', href: JobOfferController.index() },
        ],
    },
});

const statusLabels: Partial<Record<PairStatus, string>> = {
    submitted: 'Czeka na decyzję',
    invited: 'Zaproszona',
    rejected: 'Odrzucona',
};

const tones: ScheduleBarBlock['tone'][] = ['peach', 'yellow'];

function firstName(member: AnonymousCandidate): string {
    return member.anonymous_name.split(' ')[0] ?? member.anonymous_name;
}

function memberOf(pair: Pair, memberId: number): AnonymousCandidate | null {
    return pair.members.find((member) => member.id === memberId) ?? null;
}

function barBlocks(pair: Pair): ScheduleBarBlock[] {
    return pair.schedule.map((block) => {
        const member = memberOf(pair, block.candidate_profile_id);
        const index = pair.members.findIndex(
            (candidate) => candidate.id === block.candidate_profile_id,
        );

        return {
            key: block.candidate_profile_id,
            label: member ? firstName(member) : '',
            starts_at: block.starts_at,
            ends_at: block.ends_at,
            tone: tones[Math.max(0, index)] ?? 'peach',
        };
    });
}

function scheduleSummary(pair: Pair): string {
    return [...pair.schedule]
        .sort((first, second) =>
            first.starts_at.localeCompare(second.starts_at),
        )
        .map((block) => {
            const member = memberOf(pair, block.candidate_profile_id);

            return `${member ? firstName(member) : ''} ${formatHour(block.starts_at)}–${formatHour(block.ends_at)}`;
        })
        .join(', ');
}

const invitedPair = ref<Pair | null>(null);
const isInviteOpen = ref(false);
const rejectingId = ref<number | null>(null);

function openInvite(pair: Pair): void {
    invitedPair.value = pair;
    isInviteOpen.value = true;
}

function reject(pair: Pair): void {
    router.post(
        EmployerPairController.reject.url(pair.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (rejectingId.value = null),
        },
    );
}

const pendingCount = props.pairs.filter(
    (pair) => pair.status === 'submitted',
).length;
</script>

<template>
    <Head :title="`Pary job-sharing – ${offer.title}`" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
        <Link
            :href="CandidateController.index({ query: { offer: offer.id } })"
            class="inline-flex items-center gap-1 text-sm text-brand-green/70 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Kandydatki do oferty
        </Link>
        <h1 class="mt-2 text-3xl font-bold text-brand-green sm:text-4xl">
            Pary job-sharing
        </h1>
        <p class="mt-2 max-w-2xl text-sm text-brand-green/80">
            {{ offer.title }} · dzień pracy
            {{ formatHour(offer.workday_starts_at) }}–{{
                formatHour(offer.workday_ends_at)
            }}. Kandydatki same dobrały się w pary i ustaliły podział dnia.
            Widzisz je anonimowo, dopóki nie przyjmą zaproszenia.
            <template v-if="pendingCount">
                Czeka na decyzję: {{ pendingCount }}.</template
            >
        </p>

        <div
            v-if="pairs.length === 0"
            class="mt-6 rounded-3xl bg-white p-10 text-center shadow-sm"
        >
            <UsersRound class="mx-auto size-10 text-brand-mint" />
            <p class="mt-3 text-lg font-semibold text-brand-green">
                Żadna para jeszcze się nie zgłosiła
            </p>
            <p class="mt-1 text-sm text-brand-green/70">
                Gdy dwie kandydatki ustalą podział dnia i wyślą go Tobie,
                zobaczysz je tutaj.
            </p>
        </div>

        <div class="mt-6 flex flex-col gap-5">
            <article
                v-for="pair in pairs"
                :key="pair.id"
                class="rounded-3xl bg-white p-6 shadow-sm"
                :class="{ 'opacity-70': pair.status === 'rejected' }"
                data-test="job-share-pair"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="text-xl font-semibold text-brand-green">
                        {{ pair.members.map(firstName).join(' i ') }}
                    </h2>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold text-brand-green"
                        :class="
                            pair.status === 'submitted'
                                ? 'bg-brand-yellow'
                                : 'bg-brand-mint-soft'
                        "
                    >
                        {{ statusLabels[pair.status] ?? pair.status }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div
                        v-for="(member, index) in pair.members"
                        :key="member.id"
                        class="rounded-3xl p-5"
                        :class="
                            index === 0
                                ? 'bg-brand-peach/30'
                                : 'bg-brand-yellow/30'
                        "
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-full text-lg font-bold text-brand-green"
                                :class="
                                    index === 0
                                        ? 'bg-brand-peach'
                                        : 'bg-brand-yellow'
                                "
                                >{{ member.initial }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-brand-green">
                                    {{ member.anonymous_name }}
                                </p>
                                <p class="text-sm text-brand-green/70">
                                    {{ member.headline ?? '—' }}
                                    <template v-if="member.years_of_experience">
                                        ·
                                        {{ member.years_of_experience }} l.
                                        doświadczenia</template
                                    >
                                </p>
                                <p class="text-xs text-brand-green/60">
                                    Dostępna od
                                    {{ formatShortDate(member.available_from) }}
                                </p>
                            </div>
                            <span
                                v-if="member.match"
                                class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-green"
                                >{{ member.match.score }}%</span
                            >
                        </div>
                        <p
                            v-if="member.ai_summary"
                            class="mt-3 text-sm text-brand-green/80"
                        >
                            {{ member.ai_summary }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="skill in member.skills"
                                :key="skill.name"
                                class="rounded-full px-2.5 py-0.5 text-xs"
                                :class="
                                    skill.matched
                                        ? 'bg-brand-green text-white'
                                        : 'bg-white text-brand-green'
                                "
                                >{{ skill.name }}</span
                            >
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 md:grid-cols-[1fr_16rem]">
                    <div>
                        <p class="text-sm font-semibold text-brand-green">
                            Podział dnia
                        </p>
                        <div class="mt-2">
                            <ScheduleBar
                                :workday-starts-at="offer.workday_starts_at"
                                :workday-ends-at="offer.workday_ends_at"
                                :blocks="barBlocks(pair)"
                            />
                        </div>
                        <p class="mt-2 text-xs text-brand-green/70">
                            {{ scheduleSummary(pair) }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-brand-cream p-4">
                        <p class="text-sm font-semibold text-brand-green">
                            Wymagane umiejętności razem:
                            {{ pair.coverage.percent }}%
                        </p>
                        <ul class="mt-2 space-y-1 text-xs text-brand-green">
                            <li
                                v-for="skill in pair.coverage.covered"
                                :key="skill"
                                class="flex items-center gap-1.5"
                            >
                                <Check class="size-3.5 text-brand-mint" />
                                {{ skill }}
                            </li>
                            <li
                                v-for="skill in pair.coverage.missing"
                                :key="skill"
                                class="flex items-center gap-1.5 text-brand-green/50"
                            >
                                <X class="size-3.5" /> {{ skill }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    v-if="pair.status === 'submitted'"
                    class="mt-5 flex flex-wrap justify-end gap-2"
                >
                    <button
                        v-if="rejectingId !== pair.id"
                        type="button"
                        class="h-10 rounded-full border border-brand-green/30 px-5 text-sm font-medium text-brand-green hover:bg-brand-mint-soft"
                        @click="rejectingId = pair.id"
                    >
                        Odrzuć parę
                    </button>
                    <span
                        v-else
                        class="inline-flex items-center gap-2 text-sm text-brand-green"
                    >
                        Odrzucić parę?
                        <button
                            type="button"
                            class="rounded-full bg-brand-peach px-3 py-1 font-semibold"
                            @click="reject(pair)"
                        >
                            Tak
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-2 py-1 text-brand-green/70"
                            @click="rejectingId = null"
                        >
                            Nie
                        </button>
                    </span>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-brand-green px-5 text-sm font-semibold text-white hover:bg-brand-green-soft"
                        data-test="invite-pair"
                        @click="openInvite(pair)"
                    >
                        <MessageSquare class="size-4" /> Zaproś parę
                    </button>
                </div>
            </article>
        </div>

        <PairInviteDialog
            v-if="invitedPair"
            v-model:open="isInviteOpen"
            :pair-id="invitedPair.id"
            :member-names="invitedPair.members.map(firstName)"
            :offer="offer"
            :schedule-summary="scheduleSummary(invitedPair)"
        />
    </div>
</template>
