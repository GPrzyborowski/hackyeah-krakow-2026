<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MailCheck, MailX } from '@lucide/vue';
import { index } from '@/routes/blog';
import { unsubscribe } from '@/routes/newsletter';

defineProps<{
    status: 'confirmed' | 'unsubscribed';
    unsubscribeToken: string | null;
}>();
</script>

<template>
    <Head title="Newsletter" />

    <div class="mx-auto max-w-xl px-4 pt-10 pb-16 sm:px-6">
        <div
            class="flex flex-col items-center gap-4 rounded-3xl bg-white p-10 text-center text-brand-green"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full"
                :class="
                    status === 'confirmed'
                        ? 'bg-brand-yellow'
                        : 'bg-brand-mint-soft'
                "
            >
                <MailCheck v-if="status === 'confirmed'" class="size-7" />
                <MailX v-else class="size-7" />
            </span>

            <template v-if="status === 'confirmed'">
                <h1 class="text-2xl font-semibold">Zapis potwierdzony</h1>
                <p class="text-sm text-brand-green/80">
                    Raz w tygodniu wyślemy Ci jeden nowy tekst. Bez reklam i bez
                    spamu.
                </p>
                <Link
                    v-if="unsubscribeToken"
                    :href="unsubscribe(unsubscribeToken)"
                    class="text-xs text-brand-green/80 underline underline-offset-2"
                >
                    Rozmyśliłam się – wypisz mnie
                </Link>
            </template>

            <template v-else>
                <h1 class="text-2xl font-semibold">Wypisano z newslettera</h1>
                <p class="text-sm text-brand-green/80">
                    Nie wyślemy Ci więcej wiadomości. Blog jest zawsze dostępny
                    na stronie.
                </p>
            </template>

            <Link
                :href="index()"
                class="mt-2 rounded-full bg-brand-green px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft"
            >
                Przejdź do bloga
            </Link>
        </div>
    </div>
</template>
