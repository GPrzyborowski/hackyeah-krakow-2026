<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Mail, ShieldCheck } from '@lucide/vue';
import LegalPageShell from '@/components/legal/LegalPageShell.vue';
import { privacy, terms } from '@/routes/public/legal';

defineProps<{
    contactEmail: string;
}>();

const faq: { question: string; answer: string }[] = [
    {
        question: 'Czy pracodawca zobaczy, że jestem w ciąży?',
        answer: 'Nie. Termin porodu i daty urlopu są opcjonalne i prywatne – służą tylko Twojemu kalendarzowi powrotu. Pracodawca widzi jedynie datę „Dostępna od”.',
    },
    {
        question: 'Kiedy firma pozna moje imię i dane kontaktowe?',
        answer: 'Dopiero gdy przyjmiesz jej zaproszenie. Wcześniej widzi tylko anonimowy profil: doświadczenie, umiejętności i preferencje.',
    },
    {
        question: 'Czy korzystanie z MomJobs jest płatne?',
        answer: 'Dla kandydatek serwis jest bezpłatny.',
    },
    {
        question: 'Co dzieje się z moim CV?',
        answer: 'CV analizuje model AI Claude (Anthropic) jako nasz podmiot przetwarzający. Wyciąga umiejętności i przygotowuje podsumowanie, które możesz poprawić. Dane nie służą do trenowania modeli.',
    },
    {
        question: 'Jak usunąć konto i dane?',
        answer: 'W ustawieniach konta. Usunięcie konta kasuje profil, CV i daty z kalendarza powrotu.',
    },
    {
        question: 'Jak zgłosić nieodpowiednie ogłoszenie lub zachowanie firmy?',
        answer: 'Napisz do nas e-mail z nazwą firmy i opisem sytuacji. Każde zgłoszenie sprawdzamy.',
    },
];
</script>

<template>
    <LegalPageShell
        title="Kontakt"
        lead="Masz pytanie, problem albo pomysł? Napisz do nas – czytamy każdą wiadomość."
    >
        <h2>Napisz do nas</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-brand-cream p-4">
                <Mail class="size-5" aria-hidden="true" />
                <p class="mt-2! text-xs text-brand-green/80">E-mail</p>
                <p class="mt-0.5! font-semibold break-all select-all">
                    {{ contactEmail }}
                </p>
            </div>
            <div class="rounded-2xl bg-brand-cream p-4">
                <Clock class="size-5" aria-hidden="true" />
                <p class="mt-2! text-xs text-brand-green/80">Czas odpowiedzi</p>
                <p class="mt-0.5! font-semibold">do 2 dni roboczych</p>
            </div>
            <div class="rounded-2xl bg-brand-cream p-4">
                <ShieldCheck class="size-5" aria-hidden="true" />
                <p class="mt-2! text-xs text-brand-green/80">
                    Sprawy danych osobowych
                </p>
                <p class="mt-0.5! font-semibold">ten sam adres, do 30 dni</p>
            </div>
        </div>
        <p>
            Administrator serwisu: <strong>[Nazwa administratora]</strong>,
            [adres siedziby].
        </p>

        <h2>Najczęstsze pytania</h2>
        <div class="mt-4 space-y-3">
            <details
                v-for="item in faq"
                :key="item.question"
                class="group rounded-2xl border border-brand-green/10 px-4 py-3"
            >
                <summary class="cursor-pointer font-semibold">
                    {{ item.question }}
                </summary>
                <p class="mt-2! text-brand-green/80">{{ item.answer }}</p>
            </details>
        </div>

        <p>
            Więcej o danych przeczytasz w
            <Link :href="privacy()">Polityce prywatności</Link>, a o zasadach
            serwisu – w <Link :href="terms()">Regulaminie</Link>.
        </p>
    </LegalPageShell>
</template>
