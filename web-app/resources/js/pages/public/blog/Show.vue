<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles } from '@lucide/vue';
import { index as assistantIndex } from '@/routes/assistant';
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
    article: ArticleCard & { html: string };
    related: ArticleCard[];
}>();

const relatedColors = ['bg-brand-mint', 'bg-brand-yellow', 'bg-brand-peach'];
</script>

<template>
    <Head :title="article.title" />

    <article class="mx-auto max-w-3xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
        <Link
            :href="index()"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-green/70 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wszystkie teksty
        </Link>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <Link
                :href="index({ query: { category: article.category } })"
                class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-green"
            >
                {{ article.category_label }}
            </Link>
            <span class="text-xs text-brand-green/60">
                {{ article.reading_minutes }} min czytania
            </span>
        </div>

        <h1
            class="mt-4 text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl lg:text-5xl"
        >
            {{ article.title }}
        </h1>
        <p class="mt-4 text-base text-brand-green/80 sm:text-lg">
            {{ article.excerpt }}
        </p>

        <div
            class="mt-8 rounded-3xl bg-white p-6 text-brand-green shadow-sm sm:p-10 [&_a]:font-medium [&_a]:underline [&_a]:underline-offset-4 [&_blockquote]:my-5 [&_blockquote]:rounded-2xl [&_blockquote]:bg-brand-mint-soft [&_blockquote]:p-4 [&_blockquote_p]:my-0 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:font-semibold [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_p]:leading-relaxed [&_strong]:font-semibold [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6"
            data-test="article-body"
            v-html="article.html"
        />

        <Link
            :href="assistantIndex()"
            class="mt-8 flex items-center gap-4 rounded-3xl bg-brand-yellow p-6 text-brand-green transition hover:opacity-90"
        >
            <Sparkles class="size-6 shrink-0" />
            <span>
                <span class="block font-semibold"
                    >Masz pytanie o swoją sytuację?</span
                >
                <span class="text-sm text-brand-green/80">
                    Zapytaj asystenta – odpowie na podstawie przepisów i pokaże
                    źródło.
                </span>
            </span>
        </Link>

        <section v-if="related.length" class="mt-12">
            <h2 class="text-xl font-semibold text-brand-green">
                Przeczytaj też
            </h2>
            <div class="mt-4 grid gap-6 sm:grid-cols-3">
                <Link
                    v-for="(item, position) in related"
                    :key="item.id"
                    :href="show(item.slug)"
                    class="group block"
                >
                    <div
                        class="aspect-[16/10] rounded-3xl transition group-hover:opacity-90"
                        :class="relatedColors[position % relatedColors.length]"
                    />
                    <h3
                        class="mt-3 text-sm leading-snug font-semibold text-brand-green group-hover:underline"
                    >
                        {{ item.title }}
                    </h3>
                </Link>
            </div>
        </section>
    </article>
</template>
