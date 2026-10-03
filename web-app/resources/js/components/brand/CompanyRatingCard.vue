<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import RatingBar from '@/components/brand/RatingBar.vue';
import { formatRating } from '@/components/brand/format';
import { ratingCategoryLabels } from '@/components/brand/types';
import type {
    PublicCompanySummary,
    RatingCategories,
} from '@/components/brand/types';
import { show as companyShow } from '@/routes/public/companies';

defineProps<{
    company: PublicCompanySummary;
}>();

const categories = Object.keys(ratingCategoryLabels) as Array<
    keyof RatingCategories
>;
</script>

<template>
    <Link
        :href="companyShow(company.id)"
        class="flex h-full flex-col rounded-3xl bg-white p-6 transition hover:shadow-lg motion-safe:hover:-translate-y-0.5"
        data-test="company-rating-card"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-brand-green">
                    {{ company.name }}
                </h3>
                <p v-if="company.city" class="text-xs text-brand-green/80">
                    {{ company.city }}
                </p>
            </div>
            <span class="text-2xl font-semibold text-brand-green">{{
                formatRating(company.rating.overall)
            }}</span>
        </div>

        <div class="mt-5 space-y-3">
            <RatingBar
                v-for="category in categories"
                :key="category"
                :label="ratingCategoryLabels[category]"
                :value="company.rating.categories[category]"
            />
        </div>

        <figure v-if="company.featured_quote" class="mt-5 flex-1">
            <blockquote class="text-sm text-brand-green">
                {{ company.featured_quote.quote }}
            </blockquote>
            <figcaption
                v-if="company.featured_quote.author_label"
                class="mt-3 text-xs text-brand-green/80"
            >
                {{ company.featured_quote.author_label }}
            </figcaption>
        </figure>
    </Link>
</template>
