<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileText, Scale, Star } from '@lucide/vue';
import { computed } from 'vue';
import { dashboard } from '@/routes/admin';
import { index as articlesIndex } from '@/routes/admin/articles';
import { index as legalSourcesIndex } from '@/routes/admin/legal-sources';
import { index as reviewsIndex } from '@/routes/admin/reviews';

const props = defineProps<{
    stats: {
        candidates: number;
        employers: number;
        admins: number;
        published_offers: number;
        pending_reviews: number;
        accepted_invitations: number;
        job_share_pairs: number;
        articles: number;
        legal_sources: number;
        newsletter_subscribers: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

const tiles = computed(() => [
    { key: 'candidates', label: 'Kandydatki', value: props.stats.candidates },
    { key: 'employers', label: 'Pracodawcy', value: props.stats.employers },
    {
        key: 'published_offers',
        label: 'Opublikowane oferty',
        value: props.stats.published_offers,
    },
    {
        key: 'accepted_invitations',
        label: 'Przyjęte zaproszenia',
        value: props.stats.accepted_invitations,
    },
    {
        key: 'job_share_pairs',
        label: 'Pary job sharing',
        value: props.stats.job_share_pairs,
    },
    { key: 'admins', label: 'Administratorzy', value: props.stats.admins },
    {
        key: 'newsletter_subscribers',
        label: 'Subskrybenci newslettera',
        value: props.stats.newsletter_subscribers,
    },
]);

const sections = computed(() => [
    {
        key: 'reviews',
        label: 'Opinie do moderacji',
        value: props.stats.pending_reviews,
        href: reviewsIndex(),
        icon: Star,
        highlight: props.stats.pending_reviews > 0,
    },
    {
        key: 'articles',
        label: 'Artykuły',
        value: props.stats.articles,
        href: articlesIndex(),
        icon: FileText,
        highlight: false,
    },
    {
        key: 'legal_sources',
        label: 'Źródła prawne',
        value: props.stats.legal_sources,
        href: legalSourcesIndex(),
        icon: Scale,
        highlight: false,
    },
]);
</script>

<template>
    <Head title="Panel administratora" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <h1
            class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
        >
            Panel administratora
        </h1>

        <div class="grid gap-4 md:grid-cols-3">
            <Link
                v-for="section in sections"
                :key="section.key"
                :href="section.href"
                class="flex flex-col justify-between gap-4 rounded-3xl p-6 text-brand-green transition hover:shadow-md"
                :class="
                    section.highlight ? 'bg-brand-yellow' : 'bg-white shadow-sm'
                "
                :data-test="`${section.key}-tile`"
            >
                <div>
                    <p
                        class="inline-flex items-center gap-2 text-sm font-semibold"
                    >
                        <component :is="section.icon" class="size-4" />
                        {{ section.label }}
                    </p>
                    <p class="mt-1 text-4xl font-semibold">
                        {{ section.value }}
                    </p>
                </div>
                <span
                    class="inline-flex items-center gap-1 self-start rounded-full bg-brand-green px-4 py-2 text-sm font-semibold text-white"
                >
                    Przejdź <ArrowRight class="size-4" />
                </span>
            </Link>
        </div>

        <dl class="grid grid-cols-2 gap-4 md:grid-cols-3">
            <div
                v-for="tile in tiles"
                :key="tile.key"
                class="rounded-3xl bg-white p-5 shadow-sm"
            >
                <dt class="text-xs font-medium text-brand-green/70">
                    {{ tile.label }}
                </dt>
                <dd class="mt-1 text-3xl font-semibold text-brand-green">
                    {{ tile.value }}
                </dd>
            </div>
        </dl>
    </div>
</template>
