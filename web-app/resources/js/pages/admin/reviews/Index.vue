<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import ReviewCard from '@/components/reviews/ReviewCard.vue';
import ReviewStatusBadge from '@/components/reviews/ReviewStatusBadge.vue';
import type { ReviewRatings, ReviewStatus } from '@/components/reviews/types';
import { approve, index, reject } from '@/routes/admin/reviews';

type ModeratedReview = ReviewRatings & {
    id: number;
    company: { id: number; name: string };
    author_name: string | null;
    quote: string | null;
    author_label: string | null;
    status: ReviewStatus;
    created_at: string | null;
};

defineProps<{
    status: ReviewStatus;
    counts: Record<ReviewStatus, number>;
    reviews: ModeratedReview[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Opinie do moderacji', href: index() }],
    },
});

const tabs: Array<{ status: ReviewStatus; label: string }> = [
    { status: 'pending', label: 'Oczekujące' },
    { status: 'approved', label: 'Zatwierdzone' },
    { status: 'rejected', label: 'Odrzucone' },
];

const dateFormatter = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

function reviewSubtitle(review: ModeratedReview): string {
    const parts = [review.author_name ?? 'Konto usunięte'];

    if (review.created_at) {
        parts.push(dateFormatter.format(new Date(review.created_at)));
    }

    return parts.join(' · ');
}
</script>

<template>
    <Head title="Moderacja opinii" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <div>
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
            >
                Moderacja opinii
            </h1>
            <p class="mt-2 text-sm text-brand-green/80">
                Zatwierdzone opinie pojawiają się na profilu firmy. Autorka jest
                widoczna tylko dla administratorów.
            </p>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="Status opinii">
            <Link
                v-for="tab in tabs"
                :key="tab.status"
                :href="index({ query: { status: tab.status } })"
                class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="
                    tab.status === status
                        ? 'bg-brand-green text-white'
                        : 'bg-white text-brand-green hover:bg-brand-mint-soft'
                "
                :aria-current="tab.status === status ? 'page' : undefined"
            >
                {{ tab.label }}
                <span
                    class="rounded-full px-2 text-xs"
                    :class="
                        tab.status === status ? 'bg-white/20' : 'bg-brand-cream'
                    "
                    >{{ counts[tab.status] }}</span
                >
            </Link>
        </nav>

        <div
            v-if="reviews.length"
            class="grid grid-cols-1 gap-4 md:grid-cols-2"
        >
            <ReviewCard
                v-for="review in reviews"
                :key="review.id"
                :title="review.company.name"
                :subtitle="reviewSubtitle(review)"
                :ratings="review"
                :quote="review.quote"
                :author-label="review.author_label"
                :data-test="`moderated-review-${review.id}`"
            >
                <template #footer>
                    <ReviewStatusBadge :status="review.status" />
                    <div class="flex gap-2">
                        <Link
                            v-if="review.status !== 'rejected'"
                            :href="reject(review.id)"
                            as="button"
                            preserve-scroll
                            class="inline-flex items-center gap-1 rounded-full border border-brand-green px-4 py-1.5 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                        >
                            <X class="size-4" /> Odrzuć
                        </Link>
                        <Link
                            v-if="review.status !== 'approved'"
                            :href="approve(review.id)"
                            as="button"
                            preserve-scroll
                            class="inline-flex items-center gap-1 rounded-full bg-brand-green px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-green-soft"
                        >
                            <Check class="size-4" /> Zatwierdź
                        </Link>
                    </div>
                </template>
            </ReviewCard>
        </div>
        <div
            v-else
            class="rounded-3xl bg-white p-8 text-center text-sm text-brand-green/80"
        >
            Brak opinii w tej zakładce.
        </div>
    </div>
</template>
