<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, UsersRound } from '@lucide/vue';
import PairController from '@/actions/App/Http/Controllers/JobSharing/PairController';
import PartnerController from '@/actions/App/Http/Controllers/JobSharing/PartnerController';
import {
    formatHour,
    formatHours,
    midpoint,
} from '@/components/job-sharing/format';
import ScheduleBar from '@/components/job-sharing/ScheduleBar.vue';
import { pairStatusLabels } from '@/components/job-sharing/types';
import type { OfferJobSharing } from '@/components/job-sharing/types';
import { show as onboarding } from '@/routes/candidate/onboarding';

const { offerId, jobSharing } = defineProps<{
    offerId: number;
    jobSharing: OfferJobSharing;
}>();

const startsAt = jobSharing.workday_starts_at ?? '08:00';
const endsAt = jobSharing.workday_ends_at ?? '16:00';
const middle = midpoint(startsAt, endsAt);
</script>

<template>
    <section
        class="rounded-3xl bg-brand-mint-soft p-6 md:p-8"
        data-test="job-share-panel"
    >
        <div class="flex items-center gap-2 text-brand-green">
            <UsersRound class="size-5" />
            <h2 class="text-xl font-bold">
                Job sharing: jedno stanowisko, dwie osoby
            </h2>
        </div>
        <p class="mt-2 max-w-2xl text-sm text-brand-green/80">
            Dzień pracy trwa od {{ formatHour(startsAt) }} do
            {{ formatHour(endsAt) }}. Dzielicie go we dwie
            <template v-if="jobSharing.hours_per_person"
                >– mniej więcej po
                {{ formatHours(jobSharing.hours_per_person) }}</template
            >
            i same ustalacie, kto bierze którą część. Firma zobaczy Was jako
            parę, nadal anonimowo.
        </p>

        <div class="mt-5 rounded-3xl bg-white p-5">
            <ScheduleBar
                :workday-starts-at="startsAt"
                :workday-ends-at="endsAt"
                :blocks="[
                    {
                        key: 'you',
                        label: 'Ty',
                        starts_at: startsAt,
                        ends_at: middle,
                        tone: 'peach',
                    },
                    {
                        key: 'partner',
                        label: jobSharing.pair?.partner_name ?? 'Partnerka',
                        starts_at: middle,
                        ends_at: endsAt,
                        tone: 'yellow',
                    },
                ]"
            />
        </div>

        <div
            v-if="jobSharing.pair"
            class="mt-5 flex flex-col gap-3 rounded-3xl bg-white p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="font-semibold text-brand-green">
                    Twoja para:
                    {{ jobSharing.pair.partner_name ?? 'partnerka' }}
                </p>
                <p class="text-sm text-brand-green/70">
                    {{
                        jobSharing.pair.awaiting_my_answer
                            ? 'Zaprasza Cię do pary – odpowiedz na zaproszenie.'
                            : pairStatusLabels[jobSharing.pair.status]
                    }}
                </p>
            </div>
            <Link
                :href="PairController.show(jobSharing.pair.id)"
                class="inline-flex items-center gap-1.5 self-start rounded-full bg-brand-green px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft"
            >
                Przejdź do pary <ArrowRight class="size-4" />
            </Link>
        </div>

        <div v-else class="mt-5 flex flex-wrap items-center gap-3">
            <Link
                :href="PartnerController.index(offerId)"
                class="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft"
                data-test="find-partner"
            >
                Znajdź partnerkę do pary
            </Link>
            <p
                v-if="!jobSharing.is_open_to_job_sharing"
                class="text-xs text-brand-green/70"
            >
                Włącz „Jestem otwarta na job sharing” w
                <Link
                    :href="onboarding({ query: { step: 3 } })"
                    class="underline"
                    >preferencjach</Link
                >, aby inne osoby mogły Cię znaleźć.
            </p>
        </div>
    </section>
</template>
