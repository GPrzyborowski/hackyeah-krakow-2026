<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MapPin, Star } from '@lucide/vue';
import OfferCard from '@/components/brand/OfferCard.vue';
import RatingBar from '@/components/brand/RatingBar.vue';
import { formatRating } from '@/components/brand/format';
import { ratingCategoryLabels } from '@/components/brand/types';
import type {
    PublicOffer,
    RatingCategories,
    RatingSummary,
} from '@/components/brand/types';
import { reviewCountLabel } from '@/lib/plural';
import { index as offersIndex } from '@/routes/public/offers';

type Review = {
    id: number;
    rating_return: number;
    rating_flexibility: number;
    rating_no_pregnancy_questions: number;
    overall: number;
    quote: string | null;
    author_label: string | null;
};

defineProps<{
    company: {
        id: number;
        name: string;
        city: string | null;
        description: string | null;
        rating: RatingSummary;
    };
    reviews: Review[];
    offers: PublicOffer[];
}>();

const categories = Object.keys(ratingCategoryLabels) as Array<
    keyof RatingCategories
>;
</script>

<template>
    <Head :title="company.name" />

    <div class="mx-auto max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
        <Link
            :href="offersIndex()"
            class="inline-flex items-center gap-1 text-sm font-medium text-brand-green/80 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wszystkie oferty
        </Link>

        <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_22rem]">
            <section class="rounded-3xl bg-white p-6 sm:p-8">
                <h1
                    class="text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                >
                    {{ company.name }}
                </h1>
                <p
                    v-if="company.city"
                    class="mt-2 inline-flex items-center gap-1 text-sm text-brand-green/70"
                >
                    <MapPin class="size-4" /> {{ company.city }}
                </p>
                <p
                    v-if="company.description"
                    class="mt-5 text-sm leading-relaxed whitespace-pre-line text-brand-green/85"
                >
                    {{ company.description }}
                </p>
            </section>

            <aside class="rounded-3xl bg-brand-yellow p-6 text-brand-green">
                <p class="text-sm font-semibold">Jak firma traktuje rodziców</p>
                <template v-if="company.rating.count">
                    <p class="mt-3 flex items-baseline gap-2">
                        <span class="text-4xl font-semibold">{{
                            formatRating(company.rating.overall)
                        }}</span>
                        <span class="text-sm"
                            >/ 5 ·
                            {{ reviewCountLabel(company.rating.count) }}</span
                        >
                    </p>
                    <div class="mt-5 space-y-3 rounded-2xl bg-white p-4">
                        <RatingBar
                            v-for="category in categories"
                            :key="category"
                            :label="ratingCategoryLabels[category]"
                            :value="company.rating.categories[category]"
                        />
                    </div>
                </template>
                <p v-else class="mt-3 text-sm">
                    Ta firma nie ma jeszcze zatwierdzonych opinii rodziców.
                </p>
            </aside>
        </div>

        <section v-if="reviews.length" class="mt-10">
            <h2 class="text-2xl font-semibold tracking-tight text-brand-green">
                Opinie rodziców
            </h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <figure
                    v-for="review in reviews"
                    :key="review.id"
                    class="rounded-3xl bg-white p-6"
                    data-test="company-review"
                >
                    <p
                        class="inline-flex items-center gap-1 text-sm font-semibold text-brand-green"
                    >
                        <Star
                            class="size-4 fill-brand-yellow text-brand-yellow"
                        />
                        {{ formatRating(review.overall) }}
                    </p>
                    <blockquote
                        v-if="review.quote"
                        class="mt-3 text-sm text-brand-green"
                    >
                        {{ review.quote }}
                    </blockquote>
                    <figcaption
                        v-if="review.author_label"
                        class="mt-3 text-xs text-brand-green/60"
                    >
                        {{ review.author_label }}
                    </figcaption>
                    <dl
                        class="mt-4 grid grid-cols-3 gap-2 border-t border-brand-cream pt-3 text-[11px] text-brand-green/70"
                    >
                        <div>
                            <dt>Powrót</dt>
                            <dd class="font-semibold text-brand-green">
                                {{ review.rating_return }}/5
                            </dd>
                        </div>
                        <div>
                            <dt>Elastyczność</dt>
                            <dd class="font-semibold text-brand-green">
                                {{ review.rating_flexibility }}/5
                            </dd>
                        </div>
                        <div>
                            <dt>Bez pytań o ciążę</dt>
                            <dd class="font-semibold text-brand-green">
                                {{ review.rating_no_pregnancy_questions }}/5
                            </dd>
                        </div>
                    </dl>
                </figure>
            </div>
        </section>

        <section class="mt-10">
            <h2 class="text-2xl font-semibold tracking-tight text-brand-green">
                Otwarte oferty
            </h2>
            <div v-if="offers.length" class="mt-4 space-y-4">
                <OfferCard
                    v-for="offer in offers"
                    :key="offer.id"
                    :offer="offer"
                />
            </div>
            <p
                v-else
                class="mt-4 rounded-3xl bg-white p-6 text-sm text-brand-green"
            >
                Ta firma nie ma teraz opublikowanych ofert.
            </p>
        </section>
    </div>
</template>
