<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { dashboard } from '@/routes/admin';
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

        <Link
            :href="reviewsIndex()"
            class="flex items-center justify-between gap-4 rounded-3xl bg-brand-yellow p-6 text-brand-green transition hover:shadow-md"
            data-test="pending-reviews-tile"
        >
            <div>
                <p class="text-sm font-semibold">Opinie do moderacji</p>
                <p class="mt-1 text-4xl font-semibold">
                    {{ stats.pending_reviews }}
                </p>
            </div>
            <span
                class="inline-flex items-center gap-1 rounded-full bg-brand-green px-4 py-2 text-sm font-semibold text-white"
            >
                Przejdź <ArrowRight class="size-4" />
            </span>
        </Link>

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
