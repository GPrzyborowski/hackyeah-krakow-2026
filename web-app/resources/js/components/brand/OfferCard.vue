<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Heart, Star } from '@lucide/vue';
import { computed } from 'vue';
import {
    formatRating,
    formatSalary,
    formatShortDate,
} from '@/components/brand/format';
import type { PublicOffer } from '@/components/brand/types';
import VerifiedCompanyBadge from '@/components/brand/VerifiedCompanyBadge.vue';
import { jobShareLabel } from '@/components/job-sharing/format';
import { register } from '@/routes';
import { show as companyShow } from '@/routes/public/companies';

const props = defineProps<{
    offer: PublicOffer;
}>();

const page = usePage();

const isCandidate = computed(() => page.props.auth.role === 'candidate');

const salary = computed(() =>
    formatSalary(props.offer.salary_min, props.offer.salary_max),
);

const chips = computed(() =>
    [
        props.offer.job_share?.is_job_share
            ? `Job sharing · ${jobShareLabel(props.offer.job_share.hours_per_person)}`
            : null,
        props.offer.employment_fraction_label,
        props.offer.work_mode_label,
        props.offer.flexible_hours ? 'Elastyczne godziny' : null,
        props.offer.fixed_meeting_hours
            ? 'Spotkania w stałych godzinach'
            : null,
        props.offer.childcare_subsidy ? 'Dopłata do żłobka' : null,
        props.offer.nursery_distance_km != null
            ? `Przedszkole ${props.offer.nursery_distance_km} km`
            : null,
        `Start od ${formatShortDate(props.offer.start_date)}`,
    ].filter((chip): chip is string => chip !== null),
);
</script>

<template>
    <article class="rounded-3xl bg-white p-5 sm:p-6" data-test="offer-card">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0">
                <h3 class="text-xl font-semibold text-brand-green">
                    {{ offer.title }}
                </h3>
                <p class="mt-1 text-sm text-brand-green/80">
                    <Link
                        v-if="offer.company"
                        :href="companyShow(offer.company.id)"
                        class="hover:underline"
                        >{{ offer.company.name }}</Link
                    >
                    <VerifiedCompanyBadge
                        v-if="offer.company?.verified"
                        compact
                        class="ml-1 align-middle"
                    />
                    <span v-if="offer.company"> · </span>
                    <span>{{ offer.city ?? 'Polska' }}</span>
                    <span> · {{ offer.work_mode_label.toLowerCase() }}</span>
                </p>
            </div>
            <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                <span
                    v-if="offer.is_parent_friendly"
                    class="inline-flex w-fit items-center gap-1 rounded-full bg-brand-yellow px-3 py-1 text-xs font-medium text-brand-green"
                >
                    <Heart class="size-3" /> Przyjazna rodzicom
                </span>
                <span
                    v-if="salary"
                    class="text-sm font-semibold text-brand-green"
                    >{{ salary }}</span
                >
            </div>
        </div>

        <ul class="mt-4 flex flex-wrap gap-2">
            <li
                v-for="chip in chips"
                :key="chip"
                class="rounded-full bg-brand-cream px-3 py-1 text-xs text-brand-green"
            >
                {{ chip }}
            </li>
        </ul>

        <div
            class="mt-5 flex flex-col gap-4 border-t border-brand-cream pt-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p
                v-if="offer.company && offer.company.rating !== null"
                class="flex min-w-0 items-center gap-1 text-xs text-brand-green/80"
            >
                <Star class="size-3 shrink-0 fill-brand-green" />
                <span class="font-semibold whitespace-nowrap"
                    >{{ formatRating(offer.company.rating) }} z 5</span
                >
                <span v-if="offer.company.featured_quote" class="truncate">
                    · {{ offer.company.featured_quote.quote }}
                    <template v-if="offer.company.featured_quote.author_label"
                        >–
                        {{
                            offer.company.featured_quote.author_label
                        }}</template
                    >
                </span>
            </p>
            <p v-else-if="offer.company" class="text-xs text-brand-green/80">
                Firma nie ma jeszcze opinii rodziców
            </p>

            <span v-else />

            <div class="flex shrink-0 gap-2">
                <Link
                    v-if="offer.company"
                    :href="companyShow(offer.company.id)"
                    class="rounded-full border border-brand-green px-4 py-2 text-sm font-medium text-brand-green transition hover:bg-brand-cream"
                >
                    O firmie
                </Link>
                <Link
                    v-if="isCandidate"
                    href="/candidate/offers"
                    class="rounded-full bg-brand-green px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                >
                    Zobacz w panelu
                </Link>
                <Link
                    v-else-if="!page.props.auth.user"
                    :href="register()"
                    class="rounded-full bg-brand-green px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                >
                    Pokaż zainteresowanie
                </Link>
            </div>
        </div>
    </article>
</template>
