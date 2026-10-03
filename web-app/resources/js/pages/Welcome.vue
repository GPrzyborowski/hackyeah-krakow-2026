<script setup lang="ts">
import { Head, Link, usePage } from "@inertiajs/vue3";
import { ArrowRight, Send, Sparkles } from "@lucide/vue";
import { computed } from "vue";
import ArticleCard from "@/components/brand/ArticleCard.vue";
import CompanyRatingCard from "@/components/brand/CompanyRatingCard.vue";
import type {
    PublicArticleSummary,
    PublicCompanySummary,
} from "@/components/brand/types";
import { dashboard, register } from "@/routes";
import { index as candidateOffers } from "@/routes/candidate/offers";
import { create as createOffer } from "@/routes/employer/offers";
import { index as offersIndex } from "@/routes/public/offers";

defineProps<{
    companies: PublicCompanySummary[];
    articles: PublicArticleSummary[];
}>();

const page = usePage();
const isSignedIn = computed(() => Boolean(page.props.auth.user));
const isEmployer = computed(() => page.props.auth.role === "employer");

const steps = [
    {
        title: "Dodaj CV i uzupełnij profil",
        body: "AI czyta CV, wyciąga umiejętności i proponuje tagi. Ty poprawiasz i ustawiasz, co pracodawca widzi.",
    },
    {
        title: "Firmy wybierają tagi i przeglądają profile",
        body: "Pracodawca opisuje ofertę, a Ty pojawiasz się, gdy pasujesz. Do akceptacji widzi tylko imię, umiejętności i datę dostępności.",
    },
    {
        title: "Ty decydujesz, z kim rozmawiasz",
        body: "Zaproszenia przyjmujesz albo odrzucasz. Dopiero po akceptacji otwiera się czat i dane kontaktowe.",
    },
];

const interestedCompanies = [
    {
        title: "Specjalistka ds. HR",
        meta: "Zielone Biuro · 3/5 etatu · zdalnie",
        start: "start 1 wrz",
    },
    {
        title: "Koordynatorka projektów",
        meta: "Kamienica Studio · 3/4 etatu · hybrydowo",
        start: "start 15 wrz",
    },
];

const pairChat = [
    {
        author: "marta",
        text: "Mogę brać poranki. O 13:00 odbieram małą ze żłobka.",
    },
    { author: "ewa", text: "Super, ja wolę popołudnia. Biorę 12:00–16:00." },
    {
        author: "marta",
        text: "To zamieniamy się w środy, kiedy mam wizytę kontrolną?",
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
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
                <div>
                    <h1
                        class="text-4xl leading-[1.05] font-semibold tracking-tight text-brand-green sm:text-5xl lg:text-6xl"
                    >
                        Pracodawcy szukają Ciebie. Ty wybierasz, kiedy wracasz.
                    </h1>
                    <p class="mt-6 max-w-lg text-base text-brand-green/80">
                        Stwórz profil raz. Firmy z elastycznymi ofertami wybiorą
                        Cię po umiejętnościach i napiszą pierwsze. O ciąży
                        powiesz wtedy, kiedy sama zdecydujesz.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="isSignedIn ? dashboard() : register()"
                            class="rounded-full bg-brand-green px-6 py-3 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                        >
                            {{
                                isSignedIn
                                    ? "Przejdź do panelu"
                                    : "Załóż profil"
                            }}
                        </Link>
                        <Link
                            :href="offersIndex()"
                            class="rounded-full border border-brand-green px-6 py-3 text-sm font-medium text-brand-green transition hover:bg-white"
                        >
                            Przeglądaj oferty
                        </Link>
                    </div>
                    <p class="mt-6 text-xs text-brand-green/80">
                        Dla pracodawców:
                        <Link
                            :href="register({ query: { role: 'employer' } })"
                            class="font-semibold underline underline-offset-2"
                            >dodaj ogłoszenie</Link
                        >
                        – zobaczysz pasujące kandydatki, a ich dane dopiero po
                        akceptacji zaproszenia.
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
                        Oferty pojawiają się wtedy, kiedy możesz zacząć.
                    </p>

                    <div
                        class="mt-5 flex h-2.5 gap-1 overflow-hidden rounded-full"
                    >
                        <div class="w-[30%] rounded-full bg-brand-mint" />
                        <div class="w-[40%] rounded-full bg-brand-peach" />
                        <div class="w-[30%] rounded-full bg-brand-yellow" />
                    </div>
                    <div
                        class="mt-3 grid grid-cols-3 gap-2 text-[11px] leading-tight"
                    >
                        <div>
                            <p class="font-semibold">Ciąża</p>
                            <p class="text-white/80">dziś: 24. tydzień</p>
                        </div>
                        <div>
                            <p class="font-semibold">Urlop macierzyński</p>
                            <p class="text-white/80">od 14 mar 2027</p>
                        </div>
                        <div>
                            <p class="font-semibold">Gotowa</p>
                            <p class="text-white/80">od 1 wrz 2027</p>
                        </div>
                    </div>

                    <p class="mt-6 text-xs font-semibold text-brand-yellow">
                        Już zainteresowane firmy
                    </p>
                    <ul class="mt-3 space-y-2.5">
                        <li
                            v-for="item in interestedCompanies"
                            :key="item.title"
                            class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 text-brand-green"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ item.title }}
                                </p>
                                <p
                                    class="truncate text-[11px] text-brand-green/80"
                                >
                                    {{ item.meta }}
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
                        Dodaj CV – asystent AI zaproponuje kolejne oferty.
                    </p>
                </div>
            </div>
        </section>

        <!-- Reverse recruitment -->
        <section class="bg-white">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                <h2
                    class="max-w-2xl text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                >
                    Odwrócona rekrutacja: to firmy wysyłają zaproszenia
                </h2>
                <ol class="mt-10 grid gap-4 md:grid-cols-3">
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
            </div>
        </section>

        <!-- Job sharing -->
        <section class="bg-brand-mint-soft">
            <div
                class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-20"
            >
                <div>
                    <h2
                        class="text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                    >
                        Job sharing: jedno stanowisko, dwie osoby po 4 godziny
                    </h2>
                    <p class="mt-4 max-w-lg text-sm text-brand-green/80">
                        Firma zatrudnia dwie osoby na jedno stanowisko. Każda
                        pracuje pół dnia, więc w drugiej połowie możesz zająć
                        się domem i dzieckiem, a stanowisko jest obsadzone od
                        rana do popołudnia.
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
                            Jedno stanowisko, jedno wynagrodzenie na osobę, dwie
                            kandydatki, które same ustalają podział dnia.
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="candidateOffers({ query: { job_share: 1 } })"
                            class="rounded-full bg-brand-green px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                            data-test="find-partner-cta"
                            >Znajdź partnerkę do pary</Link
                        >
                        <Link
                            :href="
                                isEmployer
                                    ? createOffer({ query: { job_share: 1 } })
                                    : register({ query: { role: 'employer' } })
                            "
                            class="rounded-full border border-brand-green px-5 py-2.5 text-sm font-medium text-brand-green transition hover:bg-white"
                            data-test="job-share-offer-cta"
                            >Dodaj ofertę dla dwóch osób</Link
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
                                Czat pary: Marta i Ewa
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
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <h2
                        class="text-3xl leading-tight font-semibold tracking-tight text-brand-green sm:text-4xl"
                    >
                        Zapytaj o swoje prawa. Odpowiedź przyjdzie ze źródłem.
                    </h2>
                    <p class="mt-4 max-w-lg text-sm text-brand-green/80">
                        Asystent AI zna Kodeks pracy, przepisy o urlopach i
                        zasiłkach oraz artykuły z bloga. Przy każdej odpowiedzi
                        pokazuje, skąd ją wziął.
                    </p>
                    <Link
                        href="/assistant"
                        class="mt-6 inline-flex items-center gap-2 rounded-full bg-brand-green px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                    >
                        <Sparkles class="size-4" /> Zadaj pytanie asystentowi
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
                            href="/assistant"
                            class="mt-3 inline-flex rounded-full bg-brand-yellow px-3 py-1 text-xs font-medium text-brand-green hover:underline"
                            >Źródło: Kodeks pracy, art. 22¹</Link
                        >
                    </div>
                    <Link
                        href="/assistant"
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
                    class="mt-10 grid gap-4 md:grid-cols-3"
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
                    Pierwsze opinie mam o pracodawcach pojawią się tu już
                    wkrótce.
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
            <div class="mt-8 grid gap-6 sm:grid-cols-2 md:grid-cols-3">
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
                    class="max-w-md text-3xl leading-tight font-semibold tracking-tight text-white"
                >
                    Zacznij od profilu. Oferty znajdą Cię same.
                </h2>
                <Link
                    :href="isSignedIn ? dashboard() : register()"
                    class="w-fit shrink-0 rounded-full bg-brand-peach px-6 py-3 text-sm font-semibold text-brand-green transition hover:bg-white"
                >
                    {{ isSignedIn ? "Mój panel" : "Załóż profil" }}
                </Link>
            </div>
        </section>
    </div>
</template>
