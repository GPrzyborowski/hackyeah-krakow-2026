<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PublicArticleSummary } from '@/components/brand/types';

defineProps<{
    article: PublicArticleSummary;
}>();

const coverColors: Record<string, string> = {
    pregnancy: 'bg-brand-peach',
    leave: 'bg-brand-yellow',
    return: 'bg-brand-green',
    rights: 'bg-brand-mint-soft',
    cv_and_interviews: 'bg-brand-mint',
};
</script>

<template>
    <Link
        :href="`/blog/${article.slug}`"
        class="group block"
        data-test="article-card"
    >
        <div
            class="relative flex aspect-[16/9] items-end rounded-3xl p-4 transition group-hover:opacity-90"
            :class="coverColors[article.category] ?? 'bg-brand-mint'"
        >
            <span
                class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-green"
                >{{ article.category_label }}</span
            >
        </div>
        <h3
            class="mt-4 text-lg leading-snug font-semibold text-brand-green group-hover:underline"
        >
            {{ article.title }}
        </h3>
        <p class="mt-2 text-xs text-brand-green/60">
            {{ article.reading_minutes }} min czytania
        </p>
    </Link>
</template>
