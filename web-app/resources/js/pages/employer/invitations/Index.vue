<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Mail, MessageCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import CandidateController from '@/actions/App/Http/Controllers/Employer/CandidateController';
import InvitationController from '@/actions/App/Http/Controllers/Employer/InvitationController';
import { formatShortDate } from '@/components/employer/format';

type InvitationStatus = 'pending' | 'accepted' | 'declined' | 'withdrawn';

type InvitationRow = {
    id: number;
    status: InvitationStatus;
    message: string;
    created_at: string;
    responded_at: string | null;
    offer: { id: number; title: string };
    candidate: {
        id: number;
        anonymous_name: string;
        full_name?: string;
        email?: string;
        headline?: string | null;
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
            Imię, nazwisko i e-mail kandydatki zobaczysz, gdy zaakceptuje
            zaproszenie.
        </p>

        <div class="mt-5 flex flex-wrap gap-2">
            <button
                v-for="item in filters"
                :key="item.value"
                type="button"
                class="rounded-full px-4 py-1.5 text-sm font-medium"
                :class="
                    filter === item.value
                        ? 'bg-brand-green text-white'
                        : 'border border-brand-green/20 bg-white text-brand-green hover:bg-brand-mint-soft'
                "
                @click="filter = item.value"
            >
                {{ item.label }}
            </button>
        </div>

        <p
            v-if="visibleInvitations.length === 0"
            class="mt-6 rounded-3xl bg-white p-8 text-center text-sm text-brand-green/70 shadow-sm"
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
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-peach font-bold text-brand-green"
                >
                    {{ invitation.candidate.anonymous_name.charAt(0) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-brand-green">
                        {{
                            invitation.candidate.full_name ??
                            invitation.candidate.anonymous_name
                        }}
                    </p>
                    <p class="text-xs text-brand-green/70">
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
                </div>
            </li>
        </ul>
    </div>
</template>
