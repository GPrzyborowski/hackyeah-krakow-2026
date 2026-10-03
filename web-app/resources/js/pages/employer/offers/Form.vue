<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import JobOfferController from '@/actions/App/Http/Controllers/Employer/JobOfferController';
import InputError from '@/components/InputError.vue';
import ConversationRulesBox from '@/components/employer/ConversationRulesBox.vue';
import MatchPreviewBox from '@/components/employer/MatchPreviewBox.vue';
import ParentFriendlyChecklist from '@/components/employer/ParentFriendlyChecklist.vue';
import SkillTagInput from '@/components/employer/SkillTagInput.vue';
import type { EmployerOffer, SelectOption } from '@/components/employer/types';
import { Checkbox } from '@/components/ui/checkbox';

const props = defineProps<{
    offer: EmployerOffer | null;
    companyHasApprovedReview: boolean;
    workModes: SelectOption[];
    employmentFractions: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ogłoszenia', href: JobOfferController.index() },
        ],
    },
});

const form = useForm({
    action: 'draft' as 'draft' | 'publish',
    title: props.offer?.title ?? '',
    city: props.offer?.city ?? '',
    work_mode: props.offer?.work_mode ?? 'hybrid',
    start_date: props.offer?.start_date ?? '',
    description: props.offer?.description ?? '',
    employment_fraction: props.offer?.employment_fraction ?? '3/5',
    salary_min: (props.offer?.salary_min ?? null) as number | null,
    salary_max: (props.offer?.salary_max ?? null) as number | null,
    flexible_hours: props.offer?.flexible_hours ?? false,
    fixed_meeting_hours: props.offer?.fixed_meeting_hours ?? false,
    childcare_subsidy: props.offer?.childcare_subsidy ?? false,
    required_skills: [...(props.offer?.required_skills ?? [])],
    nice_to_have_skills: [...(props.offer?.nice_to_have_skills ?? [])],
});

const isPublished = computed(() => props.offer?.status === 'published');
const pageTitle = computed(() =>
    props.offer ? 'Edytuj ogłoszenie' : 'Dodaj ogłoszenie',
);

function submit(action: 'draft' | 'publish'): void {
    form.action = action;

    if (props.offer) {
        form.put(JobOfferController.update.url(props.offer.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(JobOfferController.store.url(), { preserveScroll: true });
}

const fieldClass =
    'mt-1.5 h-11 w-full rounded-2xl border border-brand-green/20 bg-white px-4 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-mint/50';
const labelClass = 'text-xs font-semibold text-brand-green';
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
        <Link
            :href="JobOfferController.index()"
            class="inline-flex items-center gap-1 text-sm text-brand-green/70 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wszystkie ogłoszenia
        </Link>
        <h1 class="mt-2 text-3xl font-bold text-brand-green sm:text-4xl">
            {{ pageTitle }}
        </h1>
        <p class="mt-2 max-w-xl text-sm text-brand-green/80">
            Opisz ofertę i wybierz tagi. Na ich podstawie pokażemy Ci
            kandydatki, które pasują i mogą zacząć w Twoim terminie.
        </p>

        <form
            class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]"
            @submit.prevent="submit(isPublished ? 'publish' : 'draft')"
        >
            <div class="space-y-5">
                <section class="rounded-3xl bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-green">
                        Podstawy
                    </h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-[2fr_1fr]">
                        <label :class="labelClass">
                            Nazwa stanowiska
                            <input
                                v-model="form.title"
                                type="text"
                                :class="fieldClass"
                                placeholder="np. Specjalistka ds. rekrutacji"
                                required
                            />
                            <InputError :message="form.errors.title" />
                        </label>
                        <label :class="labelClass">
                            Miasto
                            <input
                                v-model="form.city"
                                type="text"
                                :class="fieldClass"
                                placeholder="np. Poznań"
                            />
                            <InputError :message="form.errors.city" />
                        </label>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label :class="labelClass">
                            Tryb pracy
                            <select
                                v-model="form.work_mode"
                                :class="fieldClass"
                            >
                                <option
                                    v-for="mode in workModes"
                                    :key="mode.value"
                                    :value="mode.value"
                                >
                                    {{ mode.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.work_mode" />
                        </label>
                        <label :class="labelClass">
                            Planowany start
                            <input
                                v-model="form.start_date"
                                type="date"
                                :class="fieldClass"
                                required
                            />
                            <InputError :message="form.errors.start_date" />
                        </label>
                    </div>
                    <label :class="[labelClass, 'mt-4 block']">
                        Opis stanowiska
                        <textarea
                            v-model="form.description"
                            rows="4"
                            :class="[fieldClass, 'h-auto py-3']"
                            placeholder="Czym zajmuje się osoba na tym stanowisku?"
                        />
                        <InputError :message="form.errors.description" />
                    </label>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-green">
                        Warunki
                    </h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <label :class="labelClass">
                            Wymiar etatu
                            <select
                                v-model="form.employment_fraction"
                                :class="fieldClass"
                            >
                                <option
                                    v-for="fraction in employmentFractions"
                                    :key="fraction.value"
                                    :value="fraction.value"
                                >
                                    {{ fraction.label }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.employment_fraction"
                            />
                        </label>
                        <label :class="labelClass">
                            Wynagrodzenie od (zł brutto)
                            <input
                                v-model.number="form.salary_min"
                                type="number"
                                min="0"
                                step="100"
                                :class="fieldClass"
                                placeholder="8 500"
                            />
                            <InputError :message="form.errors.salary_min" />
                        </label>
                        <label :class="labelClass">
                            Wynagrodzenie do (zł brutto)
                            <input
                                v-model.number="form.salary_max"
                                type="number"
                                min="0"
                                step="100"
                                :class="fieldClass"
                                placeholder="11 000"
                            />
                            <InputError :message="form.errors.salary_max" />
                        </label>
                    </div>
                    <div class="mt-5 space-y-3 text-sm text-brand-green">
                        <label class="flex items-center gap-3">
                            <Checkbox v-model="form.flexible_hours" />
                            Elastyczne godziny pracy
                        </label>
                        <label class="flex items-center gap-3">
                            <Checkbox v-model="form.fixed_meeting_hours" />
                            Spotkania w stałych godzinach, bez wieczorów
                        </label>
                        <label class="flex items-center gap-3">
                            <Checkbox v-model="form.childcare_subsidy" />
                            Dofinansowanie żłobka lub przedszkola
                        </label>
                    </div>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-green">
                        Czego szukasz
                    </h2>
                    <p class="mt-1 text-sm text-brand-green/70">
                        Tagi decydują, które kandydatki zobaczysz. Wybierz te,
                        bez których nie da się zacząć, i te, których możesz
                        nauczyć.
                    </p>
                    <div class="mt-4 space-y-5">
                        <div>
                            <SkillTagInput
                                v-model="form.required_skills"
                                label="Wymagane"
                                variant="required"
                                :excluded="form.nice_to_have_skills"
                            />
                            <InputError
                                :message="form.errors.required_skills"
                            />
                        </div>
                        <div>
                            <SkillTagInput
                                v-model="form.nice_to_have_skills"
                                label="Mile widziane"
                                variant="nice"
                                :excluded="form.required_skills"
                            />
                            <InputError
                                :message="form.errors.nice_to_have_skills"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap justify-end gap-3">
                    <button
                        v-if="!isPublished"
                        type="button"
                        class="h-11 rounded-full border-2 border-brand-green px-6 text-sm font-semibold text-brand-green transition hover:bg-brand-mint-soft disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submit('draft')"
                    >
                        Zapisz szkic
                    </button>
                    <button
                        type="button"
                        class="h-11 rounded-full bg-brand-green px-6 text-sm font-semibold text-white transition hover:bg-brand-green-soft disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submit('publish')"
                    >
                        {{
                            isPublished
                                ? 'Zapisz zmiany'
                                : 'Opublikuj i zobacz kandydatki'
                        }}
                    </button>
                </div>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-4 lg:self-start">
                <MatchPreviewBox
                    :start-date="form.start_date"
                    :required-skills="form.required_skills"
                    :nice-to-have-skills="form.nice_to_have_skills"
                />
                <ParentFriendlyChecklist
                    :has-salary-range="
                        form.salary_min !== null &&
                        form.salary_max !== null &&
                        String(form.salary_min) !== '' &&
                        String(form.salary_max) !== ''
                    "
                    :has-flexible-hours="form.flexible_hours"
                    :has-approved-review="companyHasApprovedReview"
                />
                <ConversationRulesBox />
            </aside>
        </form>
    </div>
</template>
