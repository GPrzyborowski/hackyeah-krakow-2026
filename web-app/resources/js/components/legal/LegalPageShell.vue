<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { TriangleAlert } from "@lucide/vue";
import { contact, privacy, terms } from "@/routes/public/legal";

defineProps<{
    title: string;
    lead: string;
    updatedAt?: string;
}>();

const legalLinks = [
    { title: "Regulamin", href: terms.url() },
    { title: "Polityka prywatności", href: privacy.url() },
    { title: "Kontakt", href: contact.url() },
];
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 pt-8 pb-16 sm:px-6">
        <Head :title="title" />

        <nav
            aria-label="Dokumenty serwisu"
            class="flex flex-wrap gap-2 text-sm font-medium"
        >
            <Link
                v-for="link in legalLinks"
                :key="link.href"
                :href="link.href"
                class="rounded-full border border-brand-green/20 px-4 py-1.5 transition hover:bg-white"
                :class="{
                    'border-brand-green bg-white': $page.url === link.href,
                }"
                :aria-current="$page.url === link.href ? 'page' : undefined"
            >
                {{ link.title }}
            </Link>
        </nav>

        <h1 class="mt-8 text-3xl font-semibold text-brand-green sm:text-4xl">
            {{ title }}
        </h1>
        <p class="mt-3 text-base text-brand-green/80">{{ lead }}</p>

        <div
            role="note"
            class="mt-6 flex gap-3 rounded-2xl bg-brand-yellow/40 p-4 text-sm text-brand-green"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p>
                <strong>Wersja robocza – do weryfikacji prawnej.</strong>
                Dokument przygotowany na potrzeby prototypu (hackathon
                HackYeah). Przed uruchomieniem produkcyjnym musi go sprawdzić
                prawnik.
                <template v-if="updatedAt">
                    Ostatnia aktualizacja: {{ updatedAt }}.</template
                >
            </p>
        </div>

        <article
            class="mt-8 rounded-3xl bg-white p-6 text-sm leading-relaxed text-brand-green sm:p-10 sm:text-base [&_a]:font-medium [&_a]:underline [&_a]:underline-offset-2 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_h3]:mt-5 [&_h3]:mb-2 [&_h3]:font-semibold [&_li]:mt-1.5 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-3 [&_ul]:list-disc [&_ul]:pl-6"
        >
            <slot />
        </article>
    </div>
</template>
