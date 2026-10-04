<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Pencil, ShieldCheck, Star } from '@lucide/vue';
import RateCompanyLink from '@/components/reviews/RateCompanyLink.vue';
import ReviewCard from '@/components/reviews/ReviewCard.vue';
import ReviewStatusBadge from '@/components/reviews/ReviewStatusBadge.vue';
import type { CandidateReview } from '@/components/reviews/types';
import { edit, index } from '@/routes/reviews';

type ReviewableCompany = {
    id: number;
    name: string;
    city: string | null;
};

defineProps<{
    reviewableCompanies: ReviewableCompany[];
    reviews: Array<CandidateReview & { can_edit: boolean }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Opinie', href: index() }],
    },
});
</script>

<template>
    <Head title="Opinie o firmach" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <div>
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
            >
                Sprawdź, jak firma traktuje rodziców
            </h1>
            <p class="mt-2 text-sm text-brand-green/80">
                Twoja opinia pomaga innym mamom wybrać pracodawcę. Oceniasz
                tylko firmy, z którymi rozmawiałaś.
            </p>
        </div>

        <div
            class="flex items-start gap-3 rounded-3xl bg-brand-mint-soft p-5 text-sm text-brand-green"
        >
            <ShieldCheck class="mt-0.5 size-5 shrink-0" />
            <p>
                Opinie są anonimowe: firma nie zobaczy Twojego imienia. Każdą
                opinię sprawdzamy przed publikacją.
            </p>
        </div>

        <section>
            <h2 class="text-xl font-semibold text-brand-green">
                Firmy, które możesz ocenić
            </h2>
            <ul
                v-if="reviewableCompanies.length"
                class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2"
            >
                <li
                    v-for="company in reviewableCompanies"
                    :key="company.id"
                    class="flex items-center justify-between gap-3 rounded-3xl bg-white p-5 shadow-sm"
                    data-test="reviewable-company"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-mint-soft text-brand-green"
                        >
                            <Building2 class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-brand-green">
                                {{ company.name }}
                            </p>
                            <p
                                v-if="company.city"
                                class="text-xs text-brand-green/80"
                            >
                                {{ company.city }}
                            </p>
                        </div>
                    </div>
                    <RateCompanyLink :company-id="company.id" />
                </li>
            </ul>
            <div
                v-else
                class="mt-3 rounded-3xl bg-white p-6 text-sm text-brand-green/80"
            >
                Gdy przyjmiesz zaproszenie od firmy i porozmawiacie, będziesz
                mogła ją tutaj ocenić.
            </div>
        </section>

        <section>
            <h2 class="text-xl font-semibold text-brand-green">Twoje opinie</h2>
            <div
                v-if="reviews.length"
                class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2"
            >
                <ReviewCard
                    v-for="review in reviews"
                    :key="review.id"
                    :title="review.company.name"
                    :ratings="review"
                    :quote="review.quote"
                    :author-label="review.author_label"
                    data-test="my-review"
                >
                    <template #footer>
                        <ReviewStatusBadge :status="review.status" />
                        <Link
                            v-if="review.can_edit"
                            :href="edit(review.id)"
                            class="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-green-soft"
                        >
                            <Pencil class="size-4" /> Edytuj
                        </Link>
                    </template>
                </ReviewCard>
            </div>
            <div
                v-else
                class="mt-3 flex items-center gap-3 rounded-3xl bg-white p-6 text-sm text-brand-green/80"
            >
                <Star class="size-5 text-brand-mint" />
                Nie napisałaś jeszcze żadnej opinii.
            </div>
        </section>
    </div>
</template>
