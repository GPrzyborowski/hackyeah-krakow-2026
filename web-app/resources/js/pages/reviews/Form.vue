<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import CompanyReviewController from '@/actions/App/Http/Controllers/Reviews/CompanyReviewController';
import InputError from '@/components/InputError.vue';
import StarRatingInput from '@/components/reviews/StarRatingInput.vue';
import { ratingFields } from '@/components/reviews/types';
import type {
    CandidateReview,
    ReviewRatings,
} from '@/components/reviews/types';
import { index } from '@/routes/reviews';

const props = defineProps<{
    company: { id: number; name: string; city: string | null };
    review: CandidateReview | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Opinie', href: index() }],
    },
});

const ratings = ref<Record<keyof ReviewRatings, number | null>>({
    rating_return: props.review?.rating_return ?? null,
    rating_flexibility: props.review?.rating_flexibility ?? null,
    rating_no_pregnancy_questions:
        props.review?.rating_no_pregnancy_questions ?? null,
});
const quote = ref(props.review?.quote ?? '');

const formAction = props.review
    ? CompanyReviewController.update.form(props.review.id)
    : CompanyReviewController.store.form(props.company.id);

const fieldClass =
    'mt-1.5 w-full rounded-2xl border border-brand-line bg-white px-4 py-3 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40';
</script>

<template>
    <Head :title="`Oceń: ${company.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 md:p-8">
        <Link
            :href="index()"
            class="inline-flex items-center gap-1 text-sm font-medium text-brand-green/80 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wróć do opinii
        </Link>

        <div>
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-4xl"
            >
                {{ review ? 'Edytuj opinię' : 'Oceń firmę' }}:
                {{ company.name }}
            </h1>
            <p class="mt-2 text-sm text-brand-green/80">
                Jak firma traktuje rodziców? Opinia pojawi się na profilu firmy
                po sprawdzeniu przez nasz zespół.
            </p>
        </div>

        <Form
            v-bind="formAction"
            class="flex flex-col gap-6 rounded-3xl bg-white p-6 shadow-sm"
            v-slot="{ errors, processing }"
        >
            <StarRatingInput
                v-for="field in ratingFields"
                :key="field.key"
                v-model="ratings[field.key]"
                :name="field.key"
                :label="field.label"
                :error="errors[field.key]"
            />

            <label class="block text-sm font-semibold text-brand-green">
                Twoja opinia
                <textarea
                    v-model="quote"
                    name="quote"
                    :aria-invalid="errors.quote ? true : undefined"
                    aria-describedby="quote-error"
                    rows="4"
                    maxlength="300"
                    required
                    :class="fieldClass"
                    placeholder="Np. Po powrocie dostałam miesiąc na wdrożenie i elastyczny grafik."
                />
                <span
                    class="mt-1 flex justify-between text-xs font-normal text-brand-green/80"
                >
                    <span>Nie podawaj e-maili, telefonów ani nazwisk.</span>
                    <span>{{ quote.length }}/300</span>
                </span>
                <InputError id="quote-error" :message="errors.quote" />
            </label>

            <label class="block text-sm font-semibold text-brand-green">
                Podpis (opcjonalnie)
                <input
                    name="author_label"
                    :aria-invalid="errors.author_label ? true : undefined"
                    aria-describedby="author_label-error"
                    type="text"
                    maxlength="80"
                    :value="review?.author_label ?? ''"
                    :class="fieldClass"
                    placeholder="np. Mama dwójki, księgowość"
                />
                <span
                    class="mt-1 block text-xs font-normal text-brand-green/80"
                >
                    Bez imienia i nazwiska – wystarczy, kim jesteś i czym się
                    zajmujesz.
                </span>
                <InputError
                    id="author_label-error"
                    :message="errors.author_label"
                />
            </label>

            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-full bg-brand-green px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-60"
                    data-test="submit-review"
                >
                    {{ review ? 'Zapisz zmiany' : 'Wyślij opinię' }}
                </button>
            </div>
        </Form>
    </div>
</template>
