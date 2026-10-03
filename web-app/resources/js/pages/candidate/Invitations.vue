<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageCircle, ShieldCheck, Star } from '@lucide/vue';
import { ref } from 'vue';
import Chip from '@/components/candidate/Chip.vue';
import { formatLongDate, formatRating } from '@/components/candidate/format';
import { parentReviewCountLabel } from '@/lib/plural';
import { accept, decline, index } from '@/routes/candidate/invitations';
import { show as conversationShow } from '@/routes/conversations';
import { show as offerShow } from '@/routes/candidate/offers';

type Invitation = {
    id: number;
    status: 'pending' | 'accepted' | 'declined' | 'withdrawn';
    message: string;
    created_at: string;
    responded_at: string | null;
    conversation_id: number | null;
    job_share_pair?: { id: number; partner_name: string | null } | null;
    offer: {
        id: number;
        title: string;
        city: string | null;
        work_mode_label: string;
        employment_fraction_label: string;
        is_published: boolean;
    };
    company: {
        name: string;
        average_rating: number | null;
        reviews_count: number;
    };
};

defineProps<{ invitations: Invitation[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Zaproszenia', href: index() }],
    },
});

const statusLabels: Record<Invitation['status'], string> = {
    pending: 'Czeka na odpowiedź',
    accepted: 'Przyjęte',
    declined: 'Odrzucone',
    withdrawn: 'Wycofane przez firmę',
};

const processingId = ref<number | null>(null);

function respond(invitation: Invitation, action: 'accept' | 'decline') {
    router.visit(
        action === 'accept' ? accept(invitation.id) : decline(invitation.id),
        {
            preserveScroll: true,
            onStart: () => (processingId.value = invitation.id),
            onFinish: () => (processingId.value = null),
        },
    );
}
</script>

<template>
    <Head title="Zaproszenia" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-8">
        <h1
            class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
        >
            Zaproszenia od firm
        </h1>

        <div
            class="flex items-start gap-3 rounded-3xl bg-brand-mint-soft p-5 text-sm text-brand-green"
        >
            <ShieldCheck class="mt-0.5 size-5 shrink-0" />
            <p>
                Firmy widzą Cię anonimowo. Dopiero po akceptacji firma zobaczy
                Twoje nazwisko i e-mail.
            </p>
        </div>

        <article
            v-for="invitation in invitations"
            :key="invitation.id"
            class="rounded-3xl bg-white p-6 shadow-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link
                        v-if="invitation.offer.is_published"
                        :href="offerShow(invitation.offer.id)"
                        class="text-xl font-bold text-brand-green hover:underline"
                    >
                        {{ invitation.offer.title }}
                    </Link>
                    <p v-else class="text-xl font-bold text-brand-green">
                        {{ invitation.offer.title }}
                    </p>
                    <p class="mt-1 text-sm text-brand-green/80">
                        {{ invitation.company.name }}
                        <template v-if="invitation.offer.city">
                            · {{ invitation.offer.city }}</template
                        >
                        · {{ invitation.offer.work_mode_label.toLowerCase() }} ·
                        {{ invitation.offer.employment_fraction_label }}
                    </p>
                </div>
                <Chip
                    :tone="
                        invitation.status === 'pending'
                            ? 'yellow'
                            : invitation.status === 'accepted'
                              ? 'dark'
                              : 'soft'
                    "
                >
                    {{ statusLabels[invitation.status] }}
                </Chip>
            </div>

            <p class="mt-2 flex items-center gap-1 text-sm text-brand-green/80">
                <template v-if="invitation.company.average_rating !== null">
                    <Star
                        class="size-3.5 fill-brand-yellow text-brand-yellow"
                    />
                    {{ formatRating(invitation.company.average_rating) }} z 5 ·
                    {{
                        parentReviewCountLabel(invitation.company.reviews_count)
                    }}
                </template>
                <template v-else
                    >Firma nie ma jeszcze opinii rodziców.</template
                >
            </p>

            <div
                v-if="invitation.job_share_pair"
                class="mt-3 flex flex-wrap items-center gap-2 text-sm text-brand-green"
            >
                <Chip tone="peach">Zaproszenie dla Waszej pary</Chip>
                <span v-if="invitation.job_share_pair.partner_name"
                    >razem z {{ invitation.job_share_pair.partner_name }} ·
                    każda z Was odpowiada osobno</span
                >
            </div>

            <blockquote
                class="mt-4 rounded-2xl bg-brand-cream p-4 text-sm whitespace-pre-line text-brand-green"
            >
                {{ invitation.message }}
            </blockquote>

            <div
                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-xs text-brand-green/80">
                    Otrzymane {{ formatLongDate(invitation.created_at) }}
                    <template v-if="invitation.responded_at">
                        · odpowiedź
                        {{ formatLongDate(invitation.responded_at) }}</template
                    >
                </p>

                <div v-if="invitation.status === 'pending'" class="flex gap-2">
                    <button
                        type="button"
                        :disabled="processingId === invitation.id"
                        class="rounded-full border border-brand-green px-5 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-60"
                        @click="respond(invitation, 'decline')"
                    >
                        Odrzuć
                    </button>
                    <button
                        type="button"
                        :disabled="processingId === invitation.id"
                        class="rounded-full bg-brand-green px-5 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-60"
                        @click="respond(invitation, 'accept')"
                    >
                        Przyjmij
                    </button>
                </div>
                <Link
                    v-else-if="invitation.conversation_id"
                    :href="conversationShow(invitation.conversation_id)"
                    class="inline-flex items-center gap-1.5 self-start rounded-full bg-brand-green px-5 py-2 text-sm font-semibold text-white"
                >
                    <MessageCircle class="size-4" /> Przejdź do czatu
                </Link>
            </div>
        </article>

        <div
            v-if="invitations.length === 0"
            class="rounded-3xl bg-white p-8 text-center text-brand-green"
        >
            <p class="font-semibold">Nie masz jeszcze zaproszeń.</p>
            <p class="mt-1 text-sm text-brand-green/80">
                Gdy firma uzna, że pasujesz do oferty, zaproszenie pojawi się
                tutaj.
            </p>
        </div>
    </div>
</template>
