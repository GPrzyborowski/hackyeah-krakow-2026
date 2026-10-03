<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Mail, MessageCircle, Phone, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import CandidateController from '@/actions/App/Http/Controllers/Employer/CandidateController';
import InvitationController from '@/actions/App/Http/Controllers/Employer/InvitationController';
import CandidateAvatar from '@/components/candidate/CandidateAvatar.vue';
import { formatShortDate } from '@/components/employer/format';
import UpgradeQuestionDialog from '@/components/employer/UpgradeQuestionDialog.vue';

type InvitationStatus = 'pending' | 'accepted' | 'declined' | 'withdrawn';

type InvitationRow = {
    id: number;
    status: InvitationStatus;
    kind: 'invitation' | 'direct_message';
    kind_label: string;
    message: string;
    created_at: string;
    responded_at: string | null;
    offer: { id: number; title: string };
    candidate: {
        id: number;
        anonymous_name: string;
        full_name?: string;
        email?: string;
        phone?: string | null;
        photo_url?: string | null;
        headline?: string | null;
        career_gap_note?: string | null;
    };
    conversation_url: string | null;
};

const props = defineProps<{
    invitations: InvitationRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kandydatki', href: CandidateController.index() },
            { title: 'Zaproszenia', href: InvitationController.index() },
        ],
    },
});

const statusLabels: Record<InvitationStatus, string> = {
    pending: 'Czeka na odpowiedź',
    accepted: 'Zaakceptowane',
    declined: 'Odrzucone',
    withdrawn: 'Wycofane',
};

const statusClasses: Record<InvitationStatus, string> = {
    pending: 'bg-brand-yellow text-brand-green',
    accepted: 'bg-brand-green text-white',
    declined: 'bg-brand-peach/60 text-brand-green',
    withdrawn: 'bg-neutral-200 text-neutral-600',
};

const filter = ref<InvitationStatus | 'all'>('all');

const upgradedInvitation = ref<InvitationRow | null>(null);
const isUpgradeOpen = ref(false);

/**
 * An unanswered or ignored question can still become a regular invitation.
 */
function canInviteAfterQuestion(invitation: InvitationRow): boolean {
    return (
        invitation.kind === 'direct_message' &&
        (invitation.status === 'pending' || invitation.status === 'declined')
    );
}

function openUpgrade(invitation: InvitationRow): void {
    upgradedInvitation.value = invitation;
    isUpgradeOpen.value = true;
}

const visibleInvitations = computed(() =>
    filter.value === 'all'
        ? props.invitations
        : props.invitations.filter(
              (invitation) => invitation.status === filter.value,
          ),
);

const filters: { value: InvitationStatus | 'all'; label: string }[] = [
    { value: 'all', label: 'Wszystkie' },
    { value: 'pending', label: 'Oczekujące' },
    { value: 'accepted', label: 'Zaakceptowane' },
    { value: 'declined', label: 'Odrzucone' },
];
</script>

<template>
    <Head title="Zaproszenia" />

    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6">
        <h1 class="text-3xl font-bold text-brand-green sm:text-4xl">
            Zaproszenia
        </h1>
        <p class="mt-2 text-sm text-brand-green/80">
            Zdjęcie, nazwisko i dane kontaktowe kandydatki zobaczysz, gdy
            zaakceptuje zaproszenie.
        </p>

        <div
            class="mt-5 flex flex-wrap gap-2"
            role="group"
            aria-label="Filtruj zaproszenia"
        >
            <button
                v-for="item in filters"
                :key="item.value"
                type="button"
                :aria-pressed="filter === item.value"
                class="rounded-full px-4 py-1.5 text-sm font-medium"
                :class="
                    filter === item.value
                        ? 'bg-brand-green text-white'
                        : 'border border-brand-green/60 bg-white text-brand-green hover:bg-brand-mint-soft'
                "
                @click="filter = item.value"
            >
                {{ item.label }}
            </button>
        </div>

        <p
            v-if="visibleInvitations.length === 0"
            class="mt-6 rounded-3xl bg-white p-8 text-center text-sm text-brand-green/80 shadow-sm"
        >
            Brak zaproszeń.
            <Link
                :href="CandidateController.index()"
                class="font-semibold text-brand-green underline"
                >Przejrzyj kandydatki</Link
            >
        </p>

        <ul v-else class="mt-6 space-y-3">
            <li
                v-for="invitation in visibleInvitations"
                :key="invitation.id"
                class="flex flex-col gap-3 rounded-3xl bg-white p-5 shadow-sm sm:flex-row sm:items-center"
            >
                <CandidateAvatar
                    :name="
                        invitation.candidate.full_name ??
                        invitation.candidate.anonymous_name
                    "
                    :photo-url="invitation.candidate.photo_url"
                />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-brand-green">
                        {{
                            invitation.candidate.full_name ??
                            invitation.candidate.anonymous_name
                        }}
                    </p>
                    <p class="text-xs text-brand-green/80">
                        <template v-if="invitation.kind === 'direct_message'"
                            >{{ invitation.kind_label }} ·
                        </template>
                        {{ invitation.offer.title }} · wysłane
                        {{ formatShortDate(invitation.created_at) }}
                    </p>
                    <p
                        v-if="invitation.candidate.email"
                        class="mt-1 inline-flex items-center gap-1 text-sm text-brand-green"
                    >
                        <Mail class="size-3.5" />
                        <a
                            :href="`mailto:${invitation.candidate.email}`"
                            class="underline"
                            >{{ invitation.candidate.email }}</a
                        >
                    </p>
                    <p
                        v-if="invitation.candidate.phone"
                        class="mt-1 ml-3 inline-flex items-center gap-1 text-sm text-brand-green"
                    >
                        <Phone class="size-3.5" aria-hidden="true" />
                        <a
                            :href="`tel:${invitation.candidate.phone.replace(/\s/g, '')}`"
                            class="underline"
                            >{{ invitation.candidate.phone }}</a
                        >
                    </p>
                    <p
                        v-if="invitation.candidate.career_gap_note"
                        class="mt-1 text-sm text-brand-green/80"
                    >
                        Przerwa w karierze:
                        {{ invitation.candidate.career_gap_note }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="statusClasses[invitation.status]"
                    >
                        {{ statusLabels[invitation.status] }}
                    </span>
                    <Link
                        v-if="invitation.conversation_url"
                        :href="invitation.conversation_url"
                        class="inline-flex h-9 items-center gap-1.5 rounded-full border-2 border-brand-green px-4 text-sm font-semibold text-brand-green hover:bg-brand-mint-soft"
                    >
                        <MessageCircle class="size-4" /> Czat
                    </Link>
                    <button
                        v-if="canInviteAfterQuestion(invitation)"
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-green px-4 text-sm font-semibold text-white hover:bg-brand-green-soft"
                        data-test="invite-after-question"
                        @click="openUpgrade(invitation)"
                    >
                        <Send class="size-4" aria-hidden="true" /> Zaproś do
                        rozmowy
                    </button>
                </div>
            </li>
        </ul>

        <UpgradeQuestionDialog
            v-if="upgradedInvitation"
            v-model:open="isUpgradeOpen"
            :offer="upgradedInvitation.offer"
            :candidate="upgradedInvitation.candidate"
        />
    </div>
</template>
