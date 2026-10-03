<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import ConfirmDeleteButton from '@/components/admin/ConfirmDeleteButton.vue';
import { create, destroy, edit, index } from '@/routes/admin/legal-sources';

type LegalSourceGroup = {
    act: string;
    sources: Array<{
        id: number;
        article: string;
        title: string;
        excerpt: string;
        keywords: string[];
    }>;
};

defineProps<{
    groups: LegalSourceGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Źródła prawne', href: index() }],
    },
});
</script>

<template>
    <Head title="Źródła prawne" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1
                    class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
                >
                    Źródła prawne
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-brand-green/80">
                    Przepisy, na których opiera się asystent prawny. Zmiany
                    działają od razu: asystent dobiera przepisy po słowach
                    kluczowych, tytule i treści.
                </p>
            </div>
            <Link
                :href="create()"
                class="inline-flex h-11 items-center gap-1 rounded-full bg-brand-green px-5 text-sm font-semibold text-white transition hover:bg-brand-green-soft"
            >
                <Plus class="size-4" /> Nowe źródło
            </Link>
        </div>

        <section
            v-for="group in groups"
            :key="group.act"
            class="rounded-3xl bg-white p-6 shadow-sm"
        >
            <h2 class="text-xl font-semibold text-brand-green">
                {{ group.act }}
                <span
                    class="ml-1 rounded-full bg-brand-cream px-2 py-0.5 align-middle text-xs"
                    >{{ group.sources.length }}</span
                >
            </h2>
            <ul class="mt-4 divide-y divide-brand-green/10">
                <li
                    v-for="source in group.sources"
                    :key="source.id"
                    class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
                    :data-test="`legal-source-${source.id}`"
                >
                    <div class="min-w-0">
                        <p class="font-semibold text-brand-green">
                            art. {{ source.article }} · {{ source.title }}
                        </p>
                        <p class="mt-1 text-sm text-brand-green/70">
                            {{ source.excerpt }}
                        </p>
                        <div
                            v-if="source.keywords.length"
                            class="mt-2 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="keyword in source.keywords"
                                :key="keyword"
                                class="rounded-full bg-brand-mint-soft px-2.5 py-0.5 text-xs font-medium text-brand-green"
                                >{{ keyword }}</span
                            >
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <Link
                            :href="edit(source.id)"
                            class="inline-flex items-center gap-1 rounded-full border border-brand-green px-4 py-1.5 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                        >
                            <Pencil class="size-4" /> Edytuj
                        </Link>
                        <ConfirmDeleteButton
                            :url="destroy.url(source.id)"
                            title="Usunąć źródło prawne?"
                            :description="`${group.act}, art. ${source.article} przestanie być cytowany przez asystenta.`"
                        />
                    </div>
                </li>
            </ul>
        </section>

        <div
            v-if="!groups.length"
            class="rounded-3xl bg-white p-8 text-center text-sm text-brand-green/80"
        >
            Nie ma jeszcze żadnych źródeł prawnych.
        </div>
    </div>
</template>
