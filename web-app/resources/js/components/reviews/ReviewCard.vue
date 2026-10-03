<script setup lang="ts">
import RatingBar from '@/components/brand/RatingBar.vue';
import { formatRating } from '@/components/brand/format';
import { overallRating, ratingFields } from '@/components/reviews/types';
import type { ReviewRatings } from '@/components/reviews/types';

defineProps<{
    title: string;
    subtitle?: string | null;
    ratings: ReviewRatings;
    quote: string | null;
    authorLabel: string | null;
}>();
</script>

<template>
    <article class="flex h-full flex-col rounded-3xl bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-brand-green">
                    {{ title }}
                </h3>
                <p v-if="subtitle" class="text-xs text-brand-green/80">
                    {{ subtitle }}
                </p>
            </div>
            <span class="text-2xl font-semibold text-brand-green">{{
                formatRating(overallRating(ratings))
            }}</span>
        </div>

        <div class="mt-5 space-y-3">
            <RatingBar
                v-for="field in ratingFields"
                :key="field.key"
                :label="field.label"
                :value="ratings[field.key]"
            />
        </div>

        <figure v-if="quote" class="mt-5 flex-1">
            <blockquote class="text-sm text-brand-green">
                {{ quote }}
            </blockquote>
            <figcaption
                v-if="authorLabel"
                class="mt-3 text-xs text-brand-green/80"
            >
                {{ authorLabel }}
            </figcaption>
        </figure>

        <div
            v-if="$slots.footer"
            class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-brand-cream pt-4"
        >
            <slot name="footer" />
        </div>
    </article>
</template>
