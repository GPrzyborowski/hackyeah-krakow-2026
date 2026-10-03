<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bookmark,
    MessageSquare,
    PartyPopper,
    Plus,
    UsersRound,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CandidateController from '@/actions/App/Http/Controllers/Employer/CandidateController';
import CandidateDecisionController from '@/actions/App/Http/Controllers/Employer/CandidateDecisionController';
import InvitationController from '@/actions/App/Http/Controllers/Employer/InvitationController';
import JobOfferController from '@/actions/App/Http/Controllers/Employer/JobOfferController';
import EmployerPairController from '@/actions/App/Http/Controllers/JobSharing/EmployerPairController';
import CandidateCard from '@/components/employer/CandidateCard.vue';
import InviteDialog from '@/components/employer/InviteDialog.vue';
import MatchPanel from '@/components/employer/MatchPanel.vue';
import {
    formatShortDate,
    pluralize,
    pluralizeCandidates,
} from '@/components/employer/format';
import type {
    AnonymousCandidate,
    OfferStatistics,
    SavedCandidate,
    SwipeOffer,
} from '@/components/employer/types';

type OfferTab = OfferStatistics & { id: number; title: string };

const props = defineProps<{
    offers: OfferTab[];
    currentOffer: SwipeOffer | null;
    candidate: AnonymousCandidate | null;
    isSavedCandidate?: boolean;
    remainingCount: number;
    saved: SavedCandidate[];
    invitationStats: { invited: number; responded: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kandydatki', href: CandidateController.index() },
        ],
    },
});

const isInviteOpen = ref(false);
const isSubmitting = ref(false);

function decide(decision: 'skipped' | 'saved'): void {
    if (!props.currentOffer || !props.candidate || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        CandidateDecisionController.store.url({
            offer: props.currentOffer.id,
            candidate: props.candidate.id,
        }),
        { decision },
        {
            preserveScroll: true,
            onFinish: () => (isSubmitting.value = false),
        },
    );
}

function invite(): void {
    if (props.candidate) {
        isInviteOpen.value = true;
    }
}

const SHORTCUTS_STORAGE_KEY = 'momjobs.candidate-shortcuts';
const areShortcutsEnabled = ref(true);

const INTERACTIVE_TARGET_SELECTOR = [
    'input',
    'textarea',
    'select',
    '[contenteditable]:not([contenteditable="false"])',
    'a',
    'button',
    '[role="menu"]',
    '[role="dialog"]',
    '[role="listbox"]',
    '[role="combobox"]',
].join(', ');

function toggleShortcuts(): void {
    areShortcutsEnabled.value = !areShortcutsEnabled.value;

    try {
        window.localStorage.setItem(
            SHORTCUTS_STORAGE_KEY,
            areShortcutsEnabled.value ? 'on' : 'off',
        );
    } catch {
        // Storage can be unavailable (private mode) – the toggle still works for this visit.
    }
}

function onKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    if (
        !areShortcutsEnabled.value ||
        isInviteOpen.value ||
        event.defaultPrevented ||
        event.shiftKey ||
        event.metaKey ||
        event.ctrlKey ||
        event.altKey ||
        target?.closest(INTERACTIVE_TARGET_SELECTOR)
    ) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        decide('skipped');
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        invite();
    } else if (event.key === 'b') {
        event.preventDefault();
        decide('saved');
    }
}

const candidateAnnouncement = ref('');

const currentCandidateSummary = computed(() =>
    props.candidate
        ? [props.candidate.anonymous_name, props.candidate.headline]
              .filter(Boolean)
              .join(', ')
        : '',
);

watch(
    () => props.candidate?.id,
    (candidateId, previousId) => {
        if (candidateId === previousId) {
            return;
        }

        candidateAnnouncement.value = props.candidate
            ? `Teraz oglądasz: ${currentCandidateSummary.value}.`
            : 'Wszystkie kandydatki przejrzane.';
    },
);

onMounted(() => {
    try {
        areShortcutsEnabled.value =
            window.localStorage.getItem(SHORTCUTS_STORAGE_KEY) !== 'off';
    } catch {
        areShortcutsEnabled.value = true;
    }
});
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Kandydatki" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
        <h1 class="text-2xl font-bold text-brand-green sm:text-4xl">
            Przeglądasz kandydatki do oferty
        </h1>

        <div
            v-if="offers.length === 0"
            class="mt-6 rounded-3xl bg-white p-10 text-center shadow-sm"
        >
            <p class="text-lg font-semibold text-brand-green">
                Nie masz opublikowanych ofert
            </p>
            <p class="mt-1 text-sm text-brand-green/80">
                Opublikuj ogłoszenie, a pokażemy Ci kandydatki, które pasują i
                mogą zacząć w Twoim terminie.
            </p>
            <Link
                :href="JobOfferController.create()"
                class="mt-5 inline-flex h-11 items-center gap-2 rounded-full bg-brand-green px-5 text-sm font-semibold text-white hover:bg-brand-green-soft"
            >
                <Plus class="size-4" /> Nowe ogłoszenie
            </Link>
        </div>

        <template v-else>
            <nav
                class="-mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0"
                aria-label="Oferty"
            >
                <Link
                    v-for="offer in offers"
                    :key="offer.id"
                    :href="
                        CandidateController.index({
                            query: { offer: offer.id },
                        })
                    "
                    preserve-scroll
                    class="shrink-0 rounded-2xl px-4 py-2 text-left transition"
                    :class="
                        offer.id === currentOffer?.id
                            ? 'bg-brand-green text-white'
                            : 'border border-brand-green/60 bg-white text-brand-green hover:bg-brand-mint-soft'
                    "
                    :aria-current="
                        offer.id === currentOffer?.id ? 'page' : undefined
                    "
                >
                    <span class="block text-sm font-semibold">{{
                        offer.title
                    }}</span>
                    <span class="block text-[11px] opacity-80">
                        {{ offer.matched_count }}
                        {{ pluralizeCandidates(offer.matched_count) }} ·
                        {{ offer.to_review_count }} do przejrzenia
                    </span>
                </Link>
                <Link
                    :href="JobOfferController.create()"
                    class="inline-flex shrink-0 items-center gap-1 rounded-2xl border-2 border-brand-green px-4 py-2 text-sm font-semibold text-brand-green hover:bg-brand-mint-soft"
                >
                    <Plus class="size-4" /> Nowe ogłoszenie
                </Link>
            </nav>

            <Link
                v-if="currentOffer?.is_job_share"
                :href="EmployerPairController.index(currentOffer.id)"
                class="mt-4 flex flex-col gap-2 rounded-3xl bg-brand-yellow/60 p-4 text-brand-green transition hover:bg-brand-yellow sm:flex-row sm:items-center sm:justify-between"
                data-test="job-share-pairs-banner"
            >
                <span class="flex items-center gap-2 text-sm">
                    <UsersRound class="size-5 shrink-0" aria-hidden="true" />
                    <span
                        ><strong>Oferta job sharing.</strong> Kandydatki mogą
                        zgłaszać się parami z gotowym podziałem dnia.</span
                    >
                </span>
                <span class="text-sm font-semibold whitespace-nowrap"
                    >Pary job-sharing ·
                    {{ currentOffer.submitted_pairs_count ?? 0 }} →</span
                >
            </Link>

            <div
                class="mt-4 grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)_15rem]"
            >
                <aside class="order-3 lg:order-1">
                    <section class="rounded-3xl bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-brand-green">
                            Zapisane na później
                        </h2>
                        <p
                            v-if="saved.length === 0"
                            class="mt-3 text-sm text-brand-green/80"
                        >
                            Naciśnij „Na później”, aby wrócić do kandydatki
                            później.
                        </p>
                        <ul v-else class="mt-3 space-y-1">
                            <li v-for="item in saved" :key="item.id">
                                <Link
                                    :href="
                                        CandidateController.index({
                                            query: {
                                                offer: currentOffer?.id,
                                                candidate: item.id,
                                            },
                                        })
                                    "
                                    preserve-scroll
                                    class="flex items-center gap-3 rounded-2xl p-2 transition hover:bg-brand-cream"
                                    :class="{
                                        'bg-brand-cream':
                                            isSavedCandidate &&
                                            candidate?.id === item.id,
                                    }"
                                >
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-mint text-sm font-bold text-brand-green"
                                        >{{ item.initial }}</span
                                    >
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate text-sm font-semibold text-brand-green"
                                            >{{ item.anonymous_name }}</span
                                        >
                                        <span
                                            class="block text-[11px] text-brand-green/80"
                                            >od
                                            {{
                                                formatShortDate(
                                                    item.available_from,
                                                )
                                            }}
                                            · {{ item.score }}%</span
                                        >
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <Link
                            :href="InvitationController.index()"
                            class="mt-4 block border-t border-brand-green/10 pt-3 text-xs text-brand-green hover:underline"
                        >
                            <strong>Zaproszone:</strong>
                            {{ invitationStats.invited }}
                            {{
                                pluralize(
                                    invitationStats.invited,
                                    'osoba',
                                    'osoby',
                                    'osób',
                                )
                            }},
                            {{ invitationStats.responded }}
                            {{
                                pluralize(
                                    invitationStats.responded,
                                    'odpowiedź',
                                    'odpowiedzi',
                                    'odpowiedzi',
                                )
                            }}
                        </Link>
                    </section>
                </aside>

                <section
                    class="order-1 min-w-0 lg:order-2"
                    aria-label="Bieżąca kandydatka"
                >
                    <p class="sr-only" role="status" aria-live="polite">
                        {{ candidateAnnouncement }}
                    </p>
                    <template v-if="candidate && currentOffer">
                        <p
                            v-if="isSavedCandidate"
                            class="mb-2 text-xs font-medium text-brand-green/80"
                        >
                            Kandydatka z listy „Zapisane na później”
                        </p>
                        <CandidateCard :candidate="candidate" />

                        <div class="mt-8 flex items-start justify-center gap-6">
                            <div class="flex flex-col items-center gap-2">
                                <button
                                    type="button"
                                    class="flex size-16 items-center justify-center rounded-full border-2 border-brand-green/60 bg-white text-brand-green shadow-sm transition disabled:opacity-50 motion-safe:hover:scale-105"
                                    :disabled="isSubmitting"
                                    aria-label="Pomiń"
                                    data-test="skip-button"
                                    @click="decide('skipped')"
                                >
                                    <X class="size-6" aria-hidden="true" />
                                </button>
                                <span class="text-xs text-brand-green"
                                    >Pomiń</span
                                >
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <button
                                    type="button"
                                    class="flex size-16 items-center justify-center rounded-full bg-brand-yellow text-brand-green shadow-sm transition disabled:opacity-50 motion-safe:hover:scale-105"
                                    :disabled="isSubmitting"
                                    aria-label="Na później"
                                    @click="decide('saved')"
                                >
                                    <Bookmark
                                        class="size-6"
                                        aria-hidden="true"
                                    />
                                </button>
                                <span class="text-xs text-brand-green"
                                    >Na później</span
                                >
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <button
                                    type="button"
                                    class="flex size-20 items-center justify-center rounded-full bg-brand-green text-brand-peach shadow-md transition motion-safe:hover:scale-105"
                                    aria-label="Zaproś"
                                    @click="invite"
                                >
                                    <MessageSquare
                                        class="size-7 fill-brand-peach"
                                        aria-hidden="true"
                                    />
                                </button>
                                <span
                                    class="text-xs font-semibold text-brand-green"
                                    >Zaproś</span
                                >
                            </div>
                        </div>
                        <div
                            class="mt-6 hidden flex-col items-center gap-2 text-center text-xs text-brand-green/80 sm:flex"
                        >
                            <button
                                type="button"
                                class="rounded-full border border-brand-green/60 px-3 py-1 font-medium text-brand-green hover:bg-brand-mint-soft"
                                :aria-pressed="areShortcutsEnabled"
                                data-test="shortcuts-toggle"
                                @click="toggleShortcuts"
                            >
                                Skróty klawiszowe:
                                {{
                                    areShortcutsEnabled
                                        ? 'włączone'
                                        : 'wyłączone'
                                }}
                            </button>
                            <p v-if="areShortcutsEnabled">
                                Strzałka w lewo pomija, strzałka w prawo
                                zaprasza, B zapisuje na później.
                            </p>
                        </div>
                        <p class="mt-2 text-center text-xs text-brand-green/80">
                            W kolejce: {{ remainingCount }}
                            {{ pluralizeCandidates(remainingCount) }}
                        </p>

                        <InviteDialog
                            v-model:open="isInviteOpen"
                            :offer="currentOffer"
                            :candidate="candidate"
                        />
                    </template>
                    <div
                        v-else
                        class="flex flex-col items-center rounded-3xl bg-white p-10 text-center shadow-sm"
                        data-test="empty-state"
                    >
                        <PartyPopper
                            class="size-10 text-brand-mint"
                            aria-hidden="true"
                        />
                        <p class="mt-3 text-lg font-semibold text-brand-green">
                            Wszystkie kandydatki przejrzane
                        </p>
                        <p class="mt-1 max-w-sm text-sm text-brand-green/80">
                            Nowe osoby pojawią się tu, gdy ich profil będzie
                            pasował do oferty. Możesz też wrócić do zapisanych
                            na później albo poszerzyć tagi w ogłoszeniu.
                        </p>
                        <Link
                            v-if="currentOffer"
                            :href="JobOfferController.edit(currentOffer.id)"
                            class="mt-5 inline-flex h-10 items-center rounded-full border-2 border-brand-green px-5 text-sm font-semibold text-brand-green hover:bg-brand-mint-soft"
                        >
                            Edytuj ogłoszenie
                        </Link>
                    </div>
                </section>

                <aside class="order-2 space-y-5 lg:order-3">
                    <MatchPanel
                        v-if="candidate?.match && currentOffer"
                        :match="candidate.match"
                        :offer-start-date="currentOffer.start_date"
                    />
                    <section class="rounded-3xl bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-brand-green">
                            Pierwsza wiadomość
                        </h2>
                        <p class="mt-2 text-sm text-brand-green/80">
                            Podpowiemy, jak napisać zaproszenie: o stanowisku,
                            widełkach i godzinach pracy. Pytania o sytuację
                            rodzinną są zablokowane.
                        </p>
                    </section>
                </aside>
            </div>
        </template>
    </div>
</template>
