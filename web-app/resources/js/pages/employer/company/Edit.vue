<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Quote, Star } from '@lucide/vue';
import CompanyController from '@/actions/App/Http/Controllers/Employer/CompanyController';
import InputError from '@/components/InputError.vue';

type Company = {
    id: number;
    name: string;
    nip: string | null;
    city: string | null;
    description: string | null;
};

type Ratings = {
    count: number;
    overall: number | null;
    return: number | null;
    flexibility: number | null;
    no_pregnancy_questions: number | null;
};

type Review = {
    id: number;
    quote: string | null;
    author_label: string | null;
    rating_return: number;
    rating_flexibility: number;
    rating_no_pregnancy_questions: number;
    overall: number;
};

const props = defineProps<{
    company: Company;
    ratings: Ratings;
    reviews: Review[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Firma', href: CompanyController.edit() }],
    },
});

const categories: {
    key: keyof Omit<Ratings, 'count' | 'overall'>;
    label: string;
}[] = [
    { key: 'return', label: 'Powrót po urlopie' },
    { key: 'flexibility', label: 'Elastyczne godziny' },
    { key: 'no_pregnancy_questions', label: 'Rozmowy bez pytań o ciążę' },
];

function formatRating(value: number | null): string {
    return value === null ? '—' : value.toFixed(1).replace('.', ',');
}

function reviewCount(count: number): string {
    if (count === 1) {
        return '1 opinia';
    }

    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;
    const isFew =
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14);

    return `${count} ${isFew ? 'opinie' : 'opinii'}`;
}

const fieldClass =
    'mt-1.5 h-11 w-full rounded-2xl border border-brand-green/20 bg-white px-4 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-mint/50';
const labelClass = 'block text-xs font-semibold text-brand-green';
</script>

<template>
    <Head title="Profil firmy" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
        <h1 class="text-3xl font-bold text-brand-green sm:text-4xl">
            Profil firmy
        </h1>
        <p class="mt-2 text-sm text-brand-green/80">
            Te informacje zobaczą kandydatki przy Twoich ofertach.
        </p>

        <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <Form
                v-bind="CompanyController.update.form()"
                class="space-y-4 rounded-3xl bg-white p-6 shadow-sm"
                v-slot="{ errors, processing }"
            >
                <h2 class="text-xl font-semibold text-brand-green">
                    Dane firmy
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="labelClass">
                        Nazwa firmy
                        <input
                            name="name"
                            type="text"
                            :class="fieldClass"
                            :value="props.company.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </label>
                    <label :class="labelClass">
                        NIP (opcjonalnie)
                        <input
                            name="nip"
                            type="text"
                            inputmode="numeric"
                            :class="fieldClass"
                            :value="props.company.nip ?? ''"
                            placeholder="10 cyfr"
                        />
                        <InputError :message="errors.nip" />
                    </label>
                </div>
                <label :class="labelClass">
                    Miasto
                    <input
                        name="city"
                        type="text"
                        :class="fieldClass"
                        :value="props.company.city ?? ''"
                    />
                    <InputError :message="errors.city" />
                </label>
                <label :class="labelClass">
                    Opis firmy
                    <textarea
                        name="description"
                        rows="5"
                        :class="[fieldClass, 'h-auto py-3']"
                        :value="props.company.description ?? ''"
                        placeholder="Jak wspieracie rodziców w pracy?"
                    />
                    <InputError :message="errors.description" />
                </label>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="h-11 rounded-full bg-brand-green px-6 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                        :disabled="processing"
                    >
                        Zapisz dane firmy
                    </button>
                </div>
            </Form>

            <aside class="space-y-5">
                <section
                    class="rounded-3xl bg-brand-green p-6 text-white shadow-sm"
                >
                    <h2 class="text-lg font-semibold">Opinie rodziców</h2>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-5xl font-bold text-brand-yellow">{{
                            formatRating(ratings.overall)
                        }}</span>
                        <span class="text-sm text-white/80"
                            >średnia ocena ·
                            {{ reviewCount(ratings.count) }}</span
                        >
                    </div>
                    <ul class="mt-5 space-y-3 text-sm">
                        <li v-for="category in categories" :key="category.key">
                            <div class="flex justify-between">
                                <span class="text-white/80">{{
                                    category.label
                                }}</span>
                                <span class="font-semibold">{{
                                    formatRating(ratings[category.key])
                                }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-white/10">
                                <div
                                    class="h-full rounded-full bg-brand-peach"
                                    :style="{
                                        width: `${((ratings[category.key] ?? 0) / 5) * 100}%`,
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>

        <section class="mt-8">
            <h2 class="text-xl font-semibold text-brand-green">
                Zatwierdzone opinie
            </h2>
            <p
                v-if="reviews.length === 0"
                class="mt-3 rounded-3xl bg-white p-6 text-sm text-brand-green/70 shadow-sm"
            >
                Nie ma jeszcze zatwierdzonych opinii. Pierwsza opinia rodzica
                jest jednym z warunków odznaki „przyjazna rodzicom”.
            </p>
            <div v-else class="mt-3 grid gap-4 md:grid-cols-2">
                <article
                    v-for="review in reviews"
                    :key="review.id"
                    class="rounded-3xl bg-white p-6 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-brand-yellow px-3 py-1 text-xs font-semibold text-brand-green"
                        >
                            <Star class="size-3.5" />
                            {{ formatRating(review.overall) }}
                        </span>
                        <span class="text-xs text-brand-green/60">{{
                            review.author_label
                        }}</span>
                    </div>
                    <p
                        v-if="review.quote"
                        class="mt-3 flex gap-2 text-sm text-brand-green"
                    >
                        <Quote class="size-4 shrink-0 text-brand-mint" />
                        {{ review.quote }}
                    </p>
                    <dl
                        class="mt-4 grid grid-cols-3 gap-2 text-center text-[11px]"
                    >
                        <div class="rounded-2xl bg-brand-cream p-2">
                            <dt class="text-brand-green/70">
                                Powrót po urlopie
                            </dt>
                            <dd class="font-bold text-brand-green">
                                {{ review.rating_return }}/5
                            </dd>
                        </div>
                        <div class="rounded-2xl bg-brand-cream p-2">
                            <dt class="text-brand-green/70">
                                Elastyczne godziny
                            </dt>
                            <dd class="font-bold text-brand-green">
                                {{ review.rating_flexibility }}/5
                            </dd>
                        </div>
                        <div class="rounded-2xl bg-brand-cream p-2">
                            <dt class="text-brand-green/70">
                                Bez pytań o ciążę
                            </dt>
                            <dd class="font-bold text-brand-green">
                                {{ review.rating_no_pregnancy_questions }}/5
                            </dd>
                        </div>
                    </dl>
                </article>
            </div>
        </section>
    </div>
</template>
