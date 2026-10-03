<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import NewsletterSignup from '@/components/newsletter/NewsletterSignup.vue';
import { index, show } from '@/routes/blog';

type ArticleCard = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    category: string;
    category_label: string;
    reading_minutes: number;
    published_at: string | null;
};

defineProps<{
    categories: { value: string; label: string }[];
    activeCategory: string | null;
    featured: ArticleCard | null;
    articles: ArticleCard[];
}>();

const coverColors = [
    'bg-brand-mint',
    'bg-brand-yellow',
    'bg-brand-green',
    'bg-brand-peach',
    'bg-brand-mint-soft',
    'bg-brand-green-soft',
];

function chipClass(isActive: boolean): string {
    return isActive
        ? 'bg-brand-green text-white'
        : 'bg-white text-brand-green hover:bg-brand-mint-soft';
}
</script>

<template>
    <Head title="Blog" />

    <div class="mx-auto max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
        <h1
            class="max-w-2xl text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl lg:text-5xl"
        >
            Praca i macierzyństwo bez zgadywania
        </h1>
        <p class="mt-4 max-w-xl text-sm text-brand-green/80 sm:text-base">
            Krótkie teksty o prawach, rozmowach, CV i powrocie po urlopie. Te
            same artykuły znajdziesz w aplikacji i w odpowiedziach asystenta.
        </p>

        <nav class="mt-6 flex flex-wrap gap-2" aria-label="Kategorie artykułów">
            <Link
                :href="index()"
                preserve-scroll
                class="rounded-full px-4 py-1.5 text-xs font-medium transition"
                :class="chipClass(activeCategory === null)"
            >
                Wszystkie
            </Link>
            <Link
                v-for="category in categories"
                :key="category.value"
                :href="index({ query: { category: category.value } })"
                preserve-scroll
                class="rounded-full px-4 py-1.5 text-xs font-medium transition"
                :class="chipClass(activeCategory === category.value)"
            >
                {{ category.label }}
            </Link>
        </nav>

        <Link
            v-if="featured"
            :href="show(featured.slug)"
            class="group mt-8 grid overflow-hidden rounded-3xl md:grid-cols-2"
            data-test="featured-article"
        >
            <div
                class="flex min-h-48 items-center justify-center bg-brand-green p-10"
                aria-hidden="true"
            >
                <div class="relative h-3 w-3/5 rounded-full bg-brand-mint/60">
                    <div
                        class="absolute inset-y-0 left-0 w-2/5 rounded-full bg-brand-peach"
                    />
                    <div
                        class="absolute top-1/2 left-2/5 size-10 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-yellow"
                    />
                    <div
                        class="absolute top-1/2 left-4/5 size-6 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white"
                    />
                </div>
            </div>
            <div class="flex flex-col justify-center gap-4 bg-brand-peach p-8">
                <span
                    class="w-fit rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-green"
                >
                    {{ featured.category_label }}
                </span>
                <h2
                    class="text-2xl leading-tight font-semibold text-brand-green group-hover:underline sm:text-3xl"
                >
                    {{ featured.title }}
                </h2>
                <p class="text-sm text-brand-green/80">
                    {{ featured.excerpt }}
                </p>
                <p class="text-xs text-brand-green/80">
                    {{ featured.reading_minutes }} min czytania
                </p>
            </div>
        </Link>

        <div
            v-if="articles.length"
            class="mt-10 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Link
                v-for="(article, position) in articles"
                :key="article.id"
                :href="show(article.slug)"
                class="group block"
                data-test="article-card"
            >
                <div
                    class="flex aspect-[16/10] items-end rounded-3xl p-4 transition group-hover:opacity-90"
                    :class="coverColors[position % coverColors.length]"
                >
                    <span
                        class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-green"
                    >
                        {{ article.category_label }}
                    </span>
                </div>
                <h3
                    class="mt-4 text-lg leading-snug font-semibold text-brand-green group-hover:underline"
                >
                    {{ article.title }}
                </h3>
                <p class="mt-2 text-xs text-brand-green/80">
                    {{ article.reading_minutes }} min czytania
                </p>
            </Link>
        </div>

        <p
            v-else-if="!featured"
            class="mt-10 rounded-3xl bg-white p-10 text-center text-sm text-brand-green/80"
        >
            W tej kategorii nie ma jeszcze artykułów.
        </p>

        <NewsletterSignup class="mt-16" />
    </div>
</template>
