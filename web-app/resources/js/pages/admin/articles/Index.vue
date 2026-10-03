<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Pencil, Plus, Star } from '@lucide/vue';
import ConfirmDeleteButton from '@/components/admin/ConfirmDeleteButton.vue';
import { create, destroy, edit, index } from '@/routes/admin/articles';

type ArticleStatus = 'draft' | 'scheduled' | 'published';

type AdminArticle = {
    id: number;
    title: string;
    slug: string;
    category_label: string;
    reading_minutes: number;
    is_featured: boolean;
    status: ArticleStatus;
    published_at: string | null;
};

defineProps<{
    articles: AdminArticle[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Artykuły', href: index() }],
    },
});

const statusLabels: Record<ArticleStatus, string> = {
    draft: 'Szkic',
    scheduled: 'Zaplanowany',
    published: 'Opublikowany',
};

const statusClasses: Record<ArticleStatus, string> = {
    draft: 'bg-brand-cream text-brand-green',
    scheduled: 'bg-brand-yellow text-brand-green',
    published: 'bg-brand-mint-soft text-brand-green',
};

const dateFormatter = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});
</script>

<template>
    <Head title="Artykuły" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1
                    class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
                >
                    Artykuły
                </h1>
                <p class="mt-2 text-sm text-brand-green/80">
                    Treści bloga. Asystent prawny podpowiada opublikowane
                    artykuły w odpowiedziach.
                </p>
            </div>
            <Link
                :href="create()"
                class="inline-flex h-11 items-center gap-1 rounded-full bg-brand-green px-5 text-sm font-semibold text-white transition hover:bg-brand-green-soft"
            >
                <Plus class="size-4" /> Nowy artykuł
            </Link>
        </div>

        <ul v-if="articles.length" class="flex flex-col gap-3">
            <li
                v-for="article in articles"
                :key="article.id"
                class="flex flex-col gap-4 rounded-3xl bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                :data-test="`admin-article-${article.id}`"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span
                            class="rounded-full px-3 py-1 font-semibold"
                            :class="statusClasses[article.status]"
                        >
                            {{ statusLabels[article.status] }}
                        </span>
                        <span
                            class="rounded-full border border-brand-green/20 px-3 py-1 text-brand-green"
                        >
                            {{ article.category_label }}
                        </span>
                        <span
                            v-if="article.is_featured"
                            class="inline-flex items-center gap-1 rounded-full bg-brand-peach px-3 py-1 font-semibold text-brand-green"
                        >
                            <Star class="size-3" /> Wyróżniony
                        </span>
                    </div>
                    <h2
                        class="mt-2 truncate text-lg font-semibold text-brand-green"
                    >
                        {{ article.title }}
                    </h2>
                    <p
                        class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-brand-green/70"
                    >
                        <span>/blog/{{ article.slug }}</span>
                        <span class="inline-flex items-center gap-1">
                            <Clock class="size-3" />
                            {{ article.reading_minutes }} min czytania
                        </span>
                        <span v-if="article.published_at">
                            {{
                                dateFormatter.format(
                                    new Date(article.published_at),
                                )
                            }}
                        </span>
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link
                        :href="edit(article.id)"
                        class="inline-flex items-center gap-1 rounded-full border border-brand-green px-4 py-1.5 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                    >
                        <Pencil class="size-4" /> Edytuj
                    </Link>
                    <ConfirmDeleteButton
                        :url="destroy.url(article.id)"
                        title="Usunąć artykuł?"
                        :description="`„${article.title}” zniknie z bloga i z podpowiedzi asystenta. Tej operacji nie można cofnąć.`"
                    />
                </div>
            </li>
        </ul>
        <div
            v-else
            class="rounded-3xl bg-white p-8 text-center text-sm text-brand-green/80"
        >
            Nie ma jeszcze żadnych artykułów.
        </div>
    </div>
</template>
