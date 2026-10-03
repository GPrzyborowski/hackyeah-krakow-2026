<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, UsersRound } from '@lucide/vue';
import { ref } from 'vue';
import PairController from '@/actions/App/Http/Controllers/JobSharing/PairController';
import PartnerController from '@/actions/App/Http/Controllers/JobSharing/PartnerController';
import Chip from '@/components/candidate/Chip.vue';
import MatchPill from '@/components/candidate/MatchPill.vue';
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';
import { pairStatusLabels } from '@/components/job-sharing/types';
import type {
    JobShareSummary,
    PairStatus,
} from '@/components/job-sharing/types';
import { show as offerShow } from '@/routes/candidate/offers';
import { show as onboarding } from '@/routes/candidate/onboarding';

type PairItem = {
    id: number;
    status: PairStatus;
    offer: {
        id: number;
        title: string;
        company: string;
        job_share: JobShareSummary;
    };
    partner: {
        display_name: string;
        headline: string | null;
        preferred_day_part_label: string | null;
    } | null;
};

type ActivePairState = 'pair' | 'invite_sent' | 'invite_received';

const activePairLabels: Record<ActivePairState, string> = {
    pair: 'Twoja para',
    invite_sent: 'Zaproszenie wysłane',
    invite_received: 'Zaproszenie do pary',
};

defineProps<{
    isOpenToJobSharing: boolean;
    invitations: PairItem[];
    pairs: PairItem[];
    offers: {
        id: number;
        title: string;
        company: string;
        city: string | null;
        work_mode_label: string;
        score: number;
        job_share: JobShareSummary;
        active_pair_id: number | null;
        active_pair_state: ActivePairState | null;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Aplikuj w parze', href: PairController.index() },
        ],
    },
});

const processingId = ref<number | null>(null);

function respond(pair: PairItem, action: 'accept' | 'decline'): void {
    router.post(
        action === 'accept'
            ? PairController.accept.url(pair.id)
            : PairController.decline.url(pair.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (processingId.value = pair.id),
            onFinish: () => (processingId.value = null),
        },
    );
}
</script>

<template>
    <Head title="Job sharing" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <header>
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
            >
                Job sharing
            </h1>
            <p class="mt-2 max-w-2xl text-brand-green/80">
                Jedno stanowisko, dwie osoby po kilka godzin. Znajdź partnerkę,
                ustalcie podział dnia i aplikujcie razem.
            </p>
        </header>

        <div
            v-if="!isOpenToJobSharing"
            class="rounded-3xl bg-brand-yellow/50 p-5 text-sm text-brand-green"
        >
            Inne osoby nie widzą Cię jeszcze w wyszukiwarce partnerek. Włącz
            „Jestem otwarta na job sharing” w
            <Link
                :href="onboarding({ query: { step: 3 } })"
                class="font-semibold underline"
                >preferencjach</Link
            >.
        </div>

        <section v-if="invitations.length" class="flex flex-col gap-3">
            <h2 class="text-xl font-bold text-brand-green">
                Zaproszenia do pary
            </h2>
            <article
                v-for="invitation in invitations"
                :key="invitation.id"
                class="flex flex-col gap-4 rounded-3xl bg-brand-mint-soft p-5 sm:flex-row sm:items-center sm:justify-between"
                data-test="pair-invitation"
            >
                <div class="text-brand-green">
                    <p class="font-bold">
                        {{ invitation.partner?.display_name }} zaprasza Cię do
                        pary
                    </p>
                    <p class="text-sm text-brand-green/80">
                        {{ invitation.offer.title }} ·
                        {{ invitation.offer.company }}
                        <template
                            v-if="invitation.partner?.preferred_day_part_label"
                        >
                            · woli
                            {{
                                invitation.partner.preferred_day_part_label.toLowerCase()
                            }}</template
                        >
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="PairController.show(invitation.id)"
                        class="rounded-full px-4 py-2 text-sm font-semibold text-brand-green hover:bg-white"
                    >
                        Szczegóły
                    </Link>
                    <button
                        type="button"
                        class="rounded-full border border-brand-green px-4 py-2 text-sm font-semibold text-brand-green hover:bg-white disabled:opacity-50"
                        :disabled="processingId === invitation.id"
                        @click="respond(invitation, 'decline')"
                    >
                        Odrzuć
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-brand-green px-4 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                        :disabled="processingId === invitation.id"
                        @click="respond(invitation, 'accept')"
                    >
                        Dołączam
                    </button>
                </div>
            </article>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-xl font-bold text-brand-green">Twoje pary</h2>
            <Link
                v-for="pair in pairs"
                :key="pair.id"
                :href="PairController.show(pair.id)"
                class="flex flex-col gap-2 rounded-3xl bg-white p-5 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="font-bold text-brand-green">
                        {{ pair.offer.title }} · {{ pair.offer.company }}
                    </p>
                    <p class="text-sm text-brand-green/80">
                        Para z {{ pair.partner?.display_name ?? '—' }}
                    </p>
                </div>
                <span class="flex items-center gap-2">
                    <Chip
                        :tone="
                            pair.status === 'hired'
                                ? 'peach'
                                : pair.status === 'rejected' ||
                                    pair.status === 'declined'
                                  ? 'outline'
                                  : pair.status === 'formed'
                                    ? 'yellow'
                                    : 'dark'
                        "
                        >{{ pairStatusLabels[pair.status] }}</Chip
                    >
                    <ArrowRight class="size-4 text-brand-green" />
                </span>
            </Link>
            <p
                v-if="pairs.length === 0"
                class="rounded-3xl bg-white p-6 text-sm text-brand-green/80"
            >
                Nie masz jeszcze pary. Wybierz ofertę poniżej i znajdź
                partnerkę.
            </p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-xl font-bold text-brand-green">
                Oferty dla wielu osób
            </h2>
            <article
                v-for="offer in offers"
                :key="offer.id"
                class="flex flex-col gap-3 rounded-3xl bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <Link
                        :href="offerShow(offer.id)"
                        class="text-lg font-bold text-brand-green hover:underline"
                        >{{ offer.title }}</Link
                    >
                    <p class="text-sm text-brand-green/80">
                        {{ offer.company }}
                        <template v-if="offer.city">
                            · {{ offer.city }}</template
                        >
                        · {{ offer.work_mode_label.toLowerCase() }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <JobShareChip
                            :hours-per-person="offer.job_share.hours_per_person"
                        />
                        <MatchPill :score="offer.score" prefix="Dopasowanie " />
                    </div>
                </div>
                <Link
                    v-if="offer.active_pair_id"
                    :href="PairController.show(offer.active_pair_id)"
                    class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full border border-brand-green px-4 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                    data-test="active-pair-link"
                >
                    {{ activePairLabels[offer.active_pair_state ?? 'pair'] }}
                </Link>
                <Link
                    v-else
                    :href="PartnerController.index(offer.id)"
                    class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-brand-green px-4 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft"
                >
                    <UsersRound class="size-4" /> Znajdź partnerkę do pary
                </Link>
            </article>
            <p
                v-if="offers.length === 0"
                class="rounded-3xl bg-white p-6 text-sm text-brand-green/80"
            >
                Na razie nie ma ofert dla wielu osób.
            </p>
        </section>
    </div>
</template>
