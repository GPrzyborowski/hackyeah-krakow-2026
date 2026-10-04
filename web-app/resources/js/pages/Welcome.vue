<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Lock,
    Mail,
    Send,
    Sparkles,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import ArticleCard from '@/components/brand/ArticleCard.vue';
import CompanyRatingCard from '@/components/brand/CompanyRatingCard.vue';
import type {
    PublicArticleSummary,
    PublicCompanySummary,
} from '@/components/brand/types';
import { dashboard, register } from '@/routes';
import { index as assistantIndex } from '@/routes/assistant';
import { create as createOffer } from '@/routes/employer/offers';
import { index as jobSharingIndex } from '@/routes/job-sharing';

defineProps<{
    companies: PublicCompanySummary[];
    articles: PublicArticleSummary[];
}>();

const page = usePage();
const isSignedIn = computed(() => Boolean(page.props.auth.user));
const isEmployer = computed(() => page.props.auth.role === 'employer');
const isCandidate = computed(() => page.props.auth.role === 'candidate');

/**
 * The assistant needs an account, so guests are sent to registration instead of a login wall.
 */
const assistantHref = computed(() =>
    isSignedIn.value ? assistantIndex() : register(),
);

const steps = [
    {
        title: 'Dodaj CV i uzupełnij profil',
        body: 'AI czyta CV, wyciąga umiejętności i proponuje tagi. Ty poprawiasz i ustawiasz, co pracodawca widzi.',
    },
    {
        title: 'Firmy wybierają tagi i przeglądają profile',
        body: 'Pracodawca opisuje ofertę, a Ty pojawiasz się, gdy pasujesz. Do akceptacji widzi tylko imię z inicjałem nazwiska, umiejętności i datę dostępności.',
    },
    {
        title: 'Ty decydujesz, z kim rozmawiasz',
        body: 'Zaproszenia przyjmujesz albo odrzucasz. Dopiero po akceptacji otwiera się czat i dane kontaktowe.',
    },
];

const employerSteps = [
    {
        title: 'Opisujesz stanowisko',
        body: 'Wymiar etatu, tryb pracy, potrzebne umiejętności i najwcześniejszą datę startu.',
    },
    {
        title: 'Widzisz pasujące kandydatki',
        body: 'Bez pełnych nazwisk i zdjęć: umiejętności, doświadczenie i data, od kiedy ktoś może pracować.',
    },
    {
        title: 'Wysyłasz zaproszenie',
        body: 'Kontakt i czat otwierają się po akceptacji. Do ofert dla wielu osób kandydatki zgłaszają się też w gotowych parach.',
    },
];

const receivedInvitations = [
    {
        company: 'Zielone Biuro',
        title: 'Specjalistka ds. HR',
        meta: '3/5 etatu · zdalnie',
        start: 'start 1 wrz',
    },
    {
        company: 'Kamienica Studio',
        title: 'Koordynatorka projektów',
        meta: '3/4 etatu · hybrydowo',
        start: 'start 15 wrz',
    },
];

const pairChat = [
    {
        author: 'marta',
        text: 'Mogę brać poranki. O 13:00 odbieram małą ze żłobka.',
    },
    { author: 'ewa', text: 'Super, ja wolę popołudnia. Biorę 12:00–16:00.' },
    {
        author: 'marta',
        text: 'To zamieniamy się w środy, kiedy mam wizytę kontrolną?',
    },
] as const;
</script>

<template>
    <Head title="Praca dla przyszłych i obecnych mam" />

    <div>
        <!-- Hero -->
        <section
            class="mx-auto max-w-6xl px-4 pt-8 pb-16 sm:px-6 lg:px-8 lg:pt-14 lg:pb-24"
        >
            <div
                class="grid grid-cols-1 items-center gap-10 lg:grid-cols-2 lg:gap-14"
            >
                <div>
                    <h1
                        class="text-4xl leading-[1.05] font-semibold tracking-tight text-brand-green sm:text-5xl lg:text-6xl"
                    >
                        Firmy piszą do Ciebie pierwsze albo aplikujesz w parze z
                        drugą mamą.
                    </h1>
                    <p class="mt-6 max-w-lg text-base text-brand-green/80">
                        Tu nie ma tablicy ogłoszeń ani wysyłania CV w ciemno.
                    </p>
                    <ul
                        class="mt-6 grid max-w-lg grid-cols-1 gap-3 sm:grid-cols-2"
                    >
                        <li class="rounded-2xl bg-white p-4 text-brand-green">
                            <p
                                class="flex items-center gap-2 text-sm font-semibold"
                            >
                                <Mail class="size-4" aria-hidden="true" />
                                Firma pisze pierwsza
                            </p>
                            <p class="mt-1 text-xs text-brand-green/80">
                                Uzupełniasz profil, a pracodawcy z elastycznymi
                                stanowiskami wysyłają Ci zaproszenia.
                            </p>
                        </li>
                        <li class="rounded-2xl bg-white p-4 text-brand-green">
                            <p
                                class="flex items-center gap-2 text-sm font-semibold"
                            >
                                <UsersRound class="size-4" aria-hidden="true" />
                                Aplikujesz w parze
                            </p>
                            <p class="mt-1 text-xs text-brand-green/80">
                                Dzielisz etat z drugą mamą i razem zgłaszacie
                                się na jedno stanowisko.
                            </p>
                        </li>
                    </ul>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="isSignedIn ? dashboard() : register()"
                            class="rounded-full bg-brand-green px-6 py-3 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                        >
                            {{
                                isSignedIn
                                    ? 'Przejdź do panelu'
                                    : 'Załóż profil'
                            }}
                        </Link>
                        <a
                            href="#job-sharing"
                            class="rounded-full border border-brand-green px-6 py-3 text-sm font-medium text-brand-green transition hover:bg-white"
                        >
                            Jak działa aplikowanie w parze
                        </a>
                    </div>
                    <p class="mt-6 text-xs text-brand-green/80">
                        Zaproszenia od firm i od kandydatek do pary przychodzą
                        na maila i do aplikacji.
                    </p>
                </div>

                <!-- Return calendar demo -->
                <div
                    class="rounded-3xl bg-brand-green p-5 text-white shadow-xl sm:p-7"
                    aria-label="Przykładowy kalendarz powrotu"
                >
                    <h2 class="text-xl font-semibold">
                        Twój kalendarz powrotu
                    </h2>
                    <p class="mt-1 text-xs text-white/70">
                        Firmy widzą tylko datę, od której możesz pracować, i
                        piszą z wyprzedzeniem.
                    </p>

                    <div
                        class="mt-5 flex h-2.5 gap-1 overflow-hidden rounded-full"
                    >
                        <div class="w-[30%] rounded-full bg-brand-mint" />
                        <div class="w-[40%] rounded-full bg-brand-peach" />
                        <div class="w-[30%] rounded-full bg-brand-yellow" />
                    </div>
                    <div
                        class="mt-3 grid grid-cols-3 gap-2 text-[11px] leading-tight break-words"
                    >
                        <div>
                            <p class="font-semibold">
                                Ciąża
                                <Lock
                                    class="inline size-2.5 align-[-1px]"
                                    aria-label="tylko dla Ciebie"
                                />
                            </p>
                            <p class="text-white/80">dziś: 24. tydzień</p>
                        </div>
                        <div>
                            <p class="font-semibold">
                                Urlop macierzyński
                                <Lock
                                    class="inline size-2.5 align-[-1px]"
                                    aria-label="tylko dla Ciebie"
                                />
                            </p>
                            <p class="text-white/80">od 14 mar 2027</p>
                        </div>
                        <div>
                            <p class="font-semibold">Gotowa · widzą firmy</p>
                            <p class="text-white/80">od 1 wrz 2027</p>
                        </div>
                    </div>

                    <p
                        class="mt-6 flex items-center gap-1.5 text-xs font-semibold text-brand-yellow"
                    >
                        <Mail class="size-3.5" /> Zaproszenia od firm
                    </p>
                    <ul class="mt-3 space-y-2.5">
                        <li
                            v-for="item in receivedInvitations"
                            :key="item.title"
                            class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 text-brand-green"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-semibold sm:truncate">
                                    {{ item.company }} zaprasza na rozmowę
                                </p>
                                <p
                                    class="text-[11px] text-brand-green/80 sm:truncate"
                                >
                                    {{ item.title }} · {{ item.meta }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full bg-brand-yellow px-3 py-1 text-[11px] font-medium"
                                >{{ item.start }}</span
                            >
                        </li>
                    </ul>
                    <p
                        class="mt-3 rounded-2xl bg-white/10 px-4 py-3 text-[11px] text-white/80"
                    >
                        Firma nie zobaczy Twoich danych, dopóki się nie
                        zgodzisz.
                    </p>
                </div>
            </div>
        </section>

        <!-- Reverse recruitment -->
        <section id="jak-to-dziala" class="scroll-mt-4 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                <h2
                    class="max-w-2xl text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                >
                    Odwrócona rekrutacja: to firmy wysyłają zaproszenia
                </h2>
                <ol class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="rounded-3xl bg-brand-cream p-6"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-brand-green text-sm font-semibold text-white"
                            >{{ index + 1 }}</span
                        >
                        <h3 class="mt-5 text-lg font-semibold text-brand-green">
                            {{ step.title }}
                        </h3>
                        <p class="mt-2 text-sm text-brand-green/75">
                            {{ step.body }}
                        </p>
                    </li>
                </ol>

                <div
                    class="mt-12 rounded-3xl bg-brand-green p-6 text-white sm:p-8"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <p class="text-xs font-semibold text-brand-yellow">
                                Dla pracodawców
                            </p>
                            <h3
                                class="mt-2 max-w-xl text-2xl leading-tight font-semibold"
                            >
                                Przeglądasz anonimowe profile i sam wybierasz,
                                do kogo napisać.
                            </h3>
                        </div>
                        <Link
                            :href="
                                isEmployer
                                    ? createOffer()
                                    : register({ query: { role: 'employer' } })
                            "
                            class="w-fit shrink-0 rounded-full bg-brand-peach px-5 py-2.5 text-sm font-semibold text-brand-green transition hover:bg-white"
                            data-test="employer-cta"
                            >{{
                                isEmployer
                                    ? 'Opisz stanowisko'
                                    : 'Załóż konto firmy'
                            }}</Link
                        >
                    </div>
                    <ol class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <li
                            v-for="(step, index) in employerSteps"
                            :key="step.title"
                            class="rounded-2xl bg-white/10 p-5"
                        >
                            <p class="text-sm font-semibold">
                                {{ index + 1 }}. {{ step.title }}
                            </p>
                            <p class="mt-1.5 text-sm text-white/75">
                                {{ step.body }}
                            </p>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <!-- Job sharing -->
        <section id="job-sharing" class="scroll-mt-4 bg-brand-mint-soft">
            <div
                class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-20"
            >
                <div>
                    <h2
                        class="text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                    >
                        Aplikuj w parze na jedno stanowisko
                    </h2>
                    <p class="mt-4 max-w-lg text-sm text-brand-green/80">
                        Każda z Was pracuje część dnia, więc resztę możesz
                        poświęcić domowi i dziecku, a firma ma obsadzone
                        stanowisko od rana do popołudnia. Wybierz ofertę dla
                        wielu osób, zaproś partnerkę albo przyjmij jej
                        zaproszenie, ustalcie podział dnia i wyślijcie parę do
                        firmy. Firma zobaczy Was dopiero wtedy, gdy obie się
                        zgodzicie.
                    </p>

                    <div class="mt-6 rounded-3xl bg-white p-5">
                        <div
                            class="flex justify-between text-[11px] font-semibold text-brand-green/80"
                        >
                            <span>8:00</span><span>12:00</span
                            ><span>16:00</span>
                        </div>
                        <div
                            class="mt-2 flex h-9 gap-1 text-xs font-medium text-brand-green"
                        >
                            <div
                                class="flex w-1/2 items-center rounded-l-full bg-brand-peach px-4"
                            >
                                Marta
                            </div>
                            <div
                                class="flex w-1/2 items-center rounded-r-full bg-brand-yellow px-4"
                            >
                                Ewa
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-brand-green/80">
                            Dwie kandydatki dzielą jedno stanowisko i same
                            ustalają, kto pracuje rano, a kto po południu.
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="isCandidate ? jobSharingIndex() : register()"
                            class="rounded-full bg-brand-green px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                            data-test="find-partner-cta"
                            >Chcę pracować w parze</Link
                        >
                        <Link
                            :href="
                                isEmployer
                                    ? createOffer({ query: { job_share: 1 } })
                                    : register({ query: { role: 'employer' } })
                            "
                            class="rounded-full border border-brand-green px-5 py-2.5 text-sm font-medium text-brand-green transition hover:bg-white"
                            data-test="job-share-offer-cta"
                            >Dodaj ofertę dla wielu osób</Link
                        >
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex -space-x-2">
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-brand-peach text-xs font-semibold ring-2 ring-white"
                                >M</span
                            >
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-brand-yellow text-xs font-semibold ring-2 ring-white"
                                >E</span
                            >
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-brand-green">
                                Marta i Ewa · jeden etat na dwie
                            </p>
                            <p class="text-[11px] text-brand-green/80">
                                Specjalistka ds. rekrutacji · Zielone Biuro
                            </p>
                        </div>
                    </div>
                    <ul class="mt-5 space-y-3 text-sm">
                        <li
                            v-for="(message, index) in pairChat"
                            :key="index"
                            class="flex"
                            :class="
                                message.author === 'ewa'
                                    ? 'justify-end'
                                    : 'justify-start'
                            "
                        >
                            <p
                                class="max-w-[85%] rounded-2xl px-4 py-2.5 text-brand-green"
                                :class="
                                    message.author === 'ewa'
                                        ? 'bg-brand-yellow'
                                        : 'bg-brand-peach'
                                "
                            >
                                {{ message.text }}
                            </p>
                        </li>
                    </ul>
                    <div
                        class="mt-4 flex flex-col gap-2 rounded-2xl bg-brand-cream px-4 py-3 text-xs text-brand-green sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span>Podział: Marta 8:00–12:00, Ewa 12:00–16:00</span>
                        <span
                            class="w-fit rounded-full bg-brand-green px-3 py-1.5 text-[11px] font-medium text-white"
                            >Wyślij pracodawcy</span
                        >
                    </div>
                </div>
            </div>
        </section>

        <!-- Assistant teaser -->
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-2">
                <div>
                    <h2
                        class="text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                    >
                        Zapytaj o swoje prawa i dostań odpowiedź ze źródłem
                    </h2>
                    <p class="mt-4 max-w-lg text-sm text-brand-green/80">
                        Asystent AI zna Kodeks pracy, przepisy o urlopach i
                        zasiłkach oraz artykuły z bloga. Przy każdej odpowiedzi
                        pokazuje, skąd ją wziął.
                    </p>
                    <Link
                        :href="assistantHref"
                        class="mt-6 inline-flex items-center gap-2 rounded-full bg-brand-green px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                    >
                        <Sparkles class="size-4" />
                        {{
                            isSignedIn
                                ? 'Zadaj pytanie asystentowi'
                                : 'Załóż konto i zapytaj asystenta'
                        }}
                    </Link>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex justify-end">
                        <p
                            class="max-w-[85%] rounded-2xl bg-brand-green px-4 py-2.5 text-sm text-white"
                        >
                            Czy pracodawca może zapytać mnie o ciążę na
                            rozmowie?
                        </p>
                    </div>
                    <div
                        class="mt-3 max-w-[90%] rounded-2xl bg-brand-cream px-4 py-3 text-sm text-brand-green"
                    >
                        <p>
                            Nie. Pytanie o ciążę nie mieści się w katalogu
                            danych, których pracodawca może wymagać od
                            kandydata. Możesz odmówić odpowiedzi.
                        </p>
                        <Link
                            :href="assistantHref"
                            class="mt-3 inline-flex rounded-full bg-brand-yellow px-3 py-1 text-xs font-medium text-brand-green hover:underline"
                            >Źródło: Kodeks pracy, art. 22¹</Link
                        >
                    </div>
                    <Link
                        :href="assistantHref"
                        class="mt-5 flex items-center justify-between gap-3 rounded-full border border-brand-green/20 py-1.5 pr-1.5 pl-4 text-sm text-brand-green/80 transition hover:border-brand-green/50"
                    >
                        <span>Napisz pytanie…</span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-4 py-2 text-xs font-medium text-white"
                            ><Send class="size-3" /> Wyślij</span
                        >
                    </Link>
                </div>
            </div>
        </section>

        <!-- Company reviews -->
        <section class="bg-brand-yellow">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                <h2
                    class="max-w-2xl text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                >
                    Sprawdź, jak firma traktuje rodziców, zanim odpowiesz na
                    zaproszenie
                </h2>
                <div
                    v-if="companies.length"
                    class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-3"
                >
                    <CompanyRatingCard
                        v-for="company in companies"
                        :key="company.id"
                        :company="company"
                    />
                </div>
                <p
                    v-else
                    class="mt-8 rounded-3xl bg-white/70 p-6 text-sm text-brand-green"
                >
                    Nie ma tu jeszcze opinii mam o pracodawcach.
                </p>
            </div>
        </section>

        <!-- Blog -->
        <section
            v-if="articles.length"
            class="mx-auto max-w-6xl px-4 pt-16 sm:px-6 lg:px-8 lg:pt-20"
        >
            <div class="flex items-center justify-between gap-4">
                <h2
                    class="text-3xl font-semibold tracking-tight text-brand-green sm:text-4xl"
                >
                    Z bloga
                </h2>
                <Link
                    href="/blog"
                    class="inline-flex shrink-0 items-center gap-1 rounded-full border border-brand-green px-4 py-2 text-sm font-medium text-brand-green transition hover:bg-white"
                >
                    Wszystkie teksty <ArrowRight class="size-4" />
                </Link>
            </div>
            <div
                class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3"
            >
                <ArticleCard
                    v-for="article in articles"
                    :key="article.id"
                    :article="article"
                />
            </div>
        </section>

        <!-- Final CTA -->
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <div
                class="flex flex-col gap-6 rounded-3xl bg-brand-green px-6 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-10 lg:px-12"
            >
                <h2
                    class="max-w-md text-2xl leading-tight font-semibold tracking-tight text-white sm:text-3xl"
                >
                    Wypełnij profil raz. Zaproszenia od firm i propozycje
                    wspólnego etatu przyjdą do Ciebie.
                </h2>
                <Link
                    :href="isSignedIn ? dashboard() : register()"
                    class="w-fit shrink-0 rounded-full bg-brand-peach px-6 py-3 text-sm font-semibold text-brand-green transition hover:bg-white"
                >
                    {{ isSignedIn ? 'Mój panel' : 'Załóż profil' }}
                </Link>
            </div>
        </section>
    </div>
</template>
