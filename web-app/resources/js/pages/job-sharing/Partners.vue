<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, HeartHandshake, Sparkles, UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import PairController from '@/actions/App/Http/Controllers/JobSharing/PairController';
import Chip from '@/components/candidate/Chip.vue';
import { formatShortDate, pluralize } from '@/components/candidate/format';
import MatchPill from '@/components/candidate/MatchPill.vue';
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';
import type { JobShareSummary } from '@/components/job-sharing/types';
import InputError from '@/components/InputError.vue';
import { show as offerShow } from '@/routes/candidate/offers';
import { show as onboarding } from '@/routes/candidate/onboarding';

type Partner = {
    id: number;
    anonymous_name: string;
    initial: string;
    headline: string | null;
    available_from: string | null;
    preferred_day_part: 'morning' | 'afternoon' | 'any' | null;
    preferred_day_part_label: string | null;
    skills: { name: string; matched: boolean }[];
    score: number;
    is_interested: boolean;
    is_complementary: boolean;
};

const props = defineProps<{
    offer: {
        id: number;
        title: string;
        company: string;
        city: string | null;
        work_mode_label: string;
        job_share: JobShareSummary;
    };
    myDayPartLabel: string | null;
    isProfilePublished: boolean;
    activePairId: number | null;
    partners: Partner[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Aplikuj w parze', href: PairController.index() },
        ],
    },
});

const invitingId = ref<number | null>(null);
const error = ref<string | null>(null);

function invite(partner: Partner): void {
    router.post(
        PairController.store.url(props.offer.id),
        { partner_id: partner.id },
        {
            preserveScroll: true,
            onStart: () => {
                invitingId.value = partner.id;
                error.value = null;
            },
            onError: (errors) => (error.value = errors.partner_id ?? null),
            onFinish: () => (invitingId.value = null),
        },
    );
}

function dayPartText(partner: Partner): string | null {
    if (partner.is_complementary) {
        return `Woli ${partner.preferred_day_part_label?.toLowerCase()} – uzupełniacie się`;
    }

    return partner.preferred_day_part_label
        ? `Woli: ${partner.preferred_day_part_label.toLowerCase()}`
        : null;
}
</script>

<template>
    <Head :title="`Partnerka do pary – ${offer.title}`" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-8">
        <Link
            :href="offerShow(offer.id)"
            class="inline-flex items-center gap-1 self-start text-sm font-semibold text-brand-green hover:underline"
        >
            <ArrowLeft class="size-4" /> Wróć do oferty
        </Link>

        <header class="rounded-3xl bg-white p-6 shadow-sm md:p-8">
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-4xl"
            >
                Znajdź partnerkę do pary
            </h1>
            <p class="mt-1 text-brand-green/80">
                {{ offer.title }} · {{ offer.company }}
                <template v-if="offer.city"> · {{ offer.city }}</template>
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <JobShareChip
                    :hours-per-person="offer.job_share.hours_per_person"
                />
                <Chip v-if="myDayPartLabel"
                    >Ty wolisz: {{ myDayPartLabel.toLowerCase() }}</Chip
                >
            </div>
            <p class="mt-4 max-w-2xl text-sm text-brand-green/80">
                Pokazujemy osoby otwarte na job sharing, które pasują do oferty
                i mogą zacząć w terminie. Widzisz je tak jak pracodawca:
                anonimowo – bez nazwiska i danych kontaktowych.
            </p>
        </header>

        <div
            v-if="activePairId"
            class="flex flex-col gap-3 rounded-3xl bg-brand-mint-soft p-6 text-brand-green sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="font-semibold">Masz już parę do tej oferty.</p>
            <Link
                :href="PairController.show(activePairId)"
                class="self-start rounded-full bg-brand-green px-5 py-2.5 text-sm font-semibold text-white"
            >
                Przejdź do pary
            </Link>
        </div>

        <div
            v-else-if="!isProfilePublished"
            class="rounded-3xl bg-brand-yellow/50 p-6 text-brand-green"
        >
            <p class="font-semibold">
                Opublikuj profil, aby zaprosić kogoś do pary.
            </p>
            <Link
                :href="onboarding()"
                class="mt-3 inline-flex rounded-full bg-brand-green px-5 py-2.5 text-sm font-semibold text-white"
            >
                Uzupełnij profil
            </Link>
        </div>

        <template v-else>
            <InputError :message="error ?? undefined" />

            <p class="text-sm font-semibold text-brand-green">
                {{ partners.length }}
                {{ pluralize(partners.length, 'osoba', 'osoby', 'osób') }}
                do pary
            </p>

            <article
                v-for="partner in partners"
                :key="partner.id"
                class="rounded-3xl bg-white p-5 shadow-sm md:p-6"
                data-test="partner-card"
            >
                <div class="flex flex-wrap items-start gap-4">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-peach text-lg font-bold text-brand-green"
                        >{{ partner.initial }}</span
                    >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-brand-green">
                                {{ partner.anonymous_name }}
                            </h2>
                            <Chip v-if="partner.is_interested" tone="yellow">
                                <Sparkles class="size-3.5" /> Też interesuje się
                                tą ofertą
                            </Chip>
                        </div>
                        <p class="mt-0.5 text-sm text-brand-green/80">
                            {{ partner.headline ?? 'Bez podanego stanowiska' }}
                        </p>
                    </div>
                    <MatchPill :score="partner.score" prefix="Dopasowanie " />
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Chip
                        v-for="skill in partner.skills"
                        :key="skill.name"
                        :tone="skill.matched ? 'dark' : 'soft'"
                        >{{ skill.name }}</Chip
                    >
                </div>

                <div
                    class="mt-4 flex flex-col gap-3 border-t border-brand-cream pt-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-brand-green/80"
                            >Dostępna od
                            {{
                                formatShortDate(partner.available_from, true)
                            }}</span
                        >
                        <span
                            v-if="dayPartText(partner)"
                            class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold text-brand-green"
                            :class="
                                partner.is_complementary
                                    ? 'bg-brand-yellow'
                                    : 'bg-brand-cream'
                            "
                        >
                            <HeartHandshake
                                v-if="partner.is_complementary"
                                class="size-3.5"
                            />
                            {{ dayPartText(partner) }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 self-start rounded-full bg-brand-green px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                        :disabled="invitingId !== null"
                        @click="invite(partner)"
                    >
                        <UserPlus class="size-4" /> Zaproś do pary
                    </button>
                </div>
            </article>

            <div
                v-if="partners.length === 0"
                class="rounded-3xl bg-white p-8 text-center text-brand-green"
            >
                <p class="font-semibold">
                    Nie ma jeszcze nikogo, kto pasowałby do pary.
                </p>
                <p class="mt-1 text-sm text-brand-green/80">
                    Gdy pojawią się osoby otwarte na job sharing z podobnymi
                    umiejętnościami, zobaczysz je tutaj.
                </p>
            </div>
        </template>
    </div>
</template>
