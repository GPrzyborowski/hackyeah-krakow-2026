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
    categories: SelectOption[];
    workModes: SelectOption[];
    employmentFractions: SelectOption[];
    contractTypes: SelectOption[];
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
    category: props.offer?.category ?? '',
    city: props.offer?.city ?? '',
    work_mode: props.offer?.work_mode ?? 'hybrid',
    start_date: props.offer?.start_date ?? '',
    description: props.offer?.description ?? '',
    employment_fraction: props.offer?.employment_fraction ?? '3/5',
    contract_types: [...(props.offer?.contract_types ?? ['employment'])],
    salary_min: (props.offer?.salary_min ?? null) as number | null,
    salary_max: (props.offer?.salary_max ?? null) as number | null,
    flexible_hours: props.offer?.flexible_hours ?? false,
    fixed_meeting_hours: props.offer?.fixed_meeting_hours ?? false,
    childcare_subsidy: props.offer?.childcare_subsidy ?? false,
    nursery_distance_km: (props.offer?.nursery_distance_km ?? null) as
        | number
        | null,
    is_job_share:
        props.offer?.is_job_share ??
        (typeof window !== 'undefined' &&
            new URLSearchParams(window.location.search).get('job_share') ===
                '1'),
    workday_starts_at: props.offer?.workday_starts_at ?? '08:00',
    workday_ends_at: props.offer?.workday_ends_at ?? '16:00',
    required_skills: [...(props.offer?.required_skills ?? [])],
    nice_to_have_skills: [...(props.offer?.nice_to_have_skills ?? [])],
});

function toggleContractType(value: string, checked: boolean): void {
    form.contract_types = checked
        ? [...form.contract_types, value]
        : form.contract_types.filter((type) => type !== value);
}

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
    'mt-1.5 h-11 w-full rounded-2xl border border-brand-line bg-white px-4 text-sm font-normal text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40';
const labelClass = 'text-xs font-semibold text-brand-green';
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
        <Link
            :href="JobOfferController.index()"
            class="inline-flex items-center gap-1 text-sm text-brand-green/80 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wszystkie ogłoszenia
        </Link>
        <h1 class="mt-2 text-3xl font-bold text-brand-green sm:text-4xl">
            {{ pageTitle }}
        </h1>
        <p class="mt-2 max-w-xl text-sm text-brand-green/80">
            Opisz ogłoszenie i wybierz tagi. Na ich podstawie pokażemy Ci
            kandydatki, które pasują i mogą zacząć w Twoim terminie.
        </p>

        <form
            class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]"
            @submit.prevent="submit(isPublished ? 'publish' : 'draft')"
        >
            <div class="space-y-5">
                <section class="rounded-3xl bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-green">
                        Podstawy
                    </h2>
                    <div
                        class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-[2fr_1fr]"
                    >
                        <label :class="labelClass">
                            Nazwa stanowiska
                            <input
                                v-model="form.title"
                                :aria-invalid="
                                    form.errors.title ? true : undefined
                                "
                                aria-describedby="title-error"
                                type="text"
                                :class="fieldClass"
                                placeholder="np. Specjalistka ds. rekrutacji"
                                required
                            />
                            <InputError
                                id="title-error"
                                :message="form.errors.title"
                            />
                        </label>
                        <label :class="labelClass">
                            Miasto
                            <input
                                v-model="form.city"
                                :aria-invalid="
                                    form.errors.city ? true : undefined
                                "
                                aria-describedby="city-error"
                                type="text"
                                :class="fieldClass"
                                placeholder="np. Poznań"
                            />
                            <InputError
                                id="city-error"
                                :message="form.errors.city"
                            />
                        </label>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label :class="labelClass">
                            Branża
                            <select
                                v-model="form.category"
                                :aria-invalid="
                                    form.errors.category ? true : undefined
                                "
                                aria-describedby="category-error"
                                :class="fieldClass"
                                data-test="offer-category"
                                required
                            >
                                <option value="" disabled>Wybierz branżę</option>
                                <option
                                    v-for="category in categories"
                                    :key="category.value"
                                    :value="category.value"
                                >
                                    {{ category.label }}
                                </option>
                            </select>
                            <InputError
                                id="category-error"
                                :message="form.errors.category"
                            />
                        </label>
                        <label :class="labelClass">
                            Tryb pracy
                            <select
                                v-model="form.work_mode"
                                :aria-invalid="
                                    form.errors.work_mode ? true : undefined
                                "
                                aria-describedby="work_mode-error"
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
                            <InputError
                                id="work_mode-error"
                                :message="form.errors.work_mode"
                            />
                        </label>
                        <label :class="labelClass">
                            Planowany start
                            <input
                                v-model="form.start_date"
                                :aria-invalid="
                                    form.errors.start_date ? true : undefined
                                "
                                aria-describedby="start_date-error"
                                type="date"
                                :class="fieldClass"
                                required
                            />
                            <InputError
                                id="start_date-error"
                                :message="form.errors.start_date"
                            />
                        </label>
                    </div>
                    <label :class="[labelClass, 'mt-4 block']">
                        Opis stanowiska
                        <textarea
                            v-model="form.description"
                            :aria-invalid="
                                form.errors.description ? true : undefined
                            "
                            aria-describedby="description-error"
                            rows="4"
                            :class="[fieldClass, 'h-auto py-3']"
                            placeholder="Czym zajmuje się osoba na tym stanowisku?"
                        />
                        <InputError
                            id="description-error"
                            :message="form.errors.description"
                        />
                    </label>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-green">
                        Warunki
                    </h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label :class="labelClass">
                            Wymiar etatu
                            <select
                                v-model="form.employment_fraction"
                                :aria-invalid="
                                    form.errors.employment_fraction
                                        ? true
                                        : undefined
                                "
                                aria-describedby="employment_fraction-error"
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
                                id="employment_fraction-error"
                                :message="form.errors.employment_fraction"
                            />
                        </label>
                        <label :class="labelClass">
                            Wynagrodzenie od (zł brutto)
                            <input
                                v-model.number="form.salary_min"
                                :aria-invalid="
                                    form.errors.salary_min ? true : undefined
                                "
                                aria-describedby="salary_min-error"
                                type="number"
                                min="0"
                                step="100"
                                :class="fieldClass"
                                placeholder="8 500"
                            />
                            <InputError
                                id="salary_min-error"
                                :message="form.errors.salary_min"
                            />
                        </label>
                        <label :class="labelClass">
                            Wynagrodzenie do (zł brutto)
                            <input
                                v-model.number="form.salary_max"
                                :aria-invalid="
                                    form.errors.salary_max ? true : undefined
                                "
                                aria-describedby="salary_max-error"
                                type="number"
                                min="0"
                                step="100"
                                :class="fieldClass"
                                placeholder="11 000"
                            />
                            <InputError
                                id="salary_max-error"
                                :message="form.errors.salary_max"
                            />
                        </label>
                    </div>
                    <fieldset
                        class="mt-5"
                        :aria-invalid="
                            form.errors.contract_types ? true : undefined
                        "
                        aria-describedby="contract_types-error"
                        data-test="offer-contract-types"
                    >
                        <legend :class="labelClass">Forma zatrudnienia</legend>
                        <div
                            class="mt-2 flex flex-wrap gap-x-6 gap-y-3 text-sm text-brand-green"
                        >
                            <label
                                v-for="contractType in contractTypes"
                                :key="contractType.value"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :model-value="
                                        form.contract_types.includes(
                                            contractType.value,
                                        )
                                    "
                                    :data-test="`offer-contract-type-${contractType.value}`"
                                    @update:model-value="
                                        (checked) =>
                                            toggleContractType(
                                                contractType.value,
                                                checked === true,
                                            )
                                    "
                                />
                                {{ contractType.label }}
                            </label>
                        </div>
                        <InputError
                            id="contract_types-error"
                            :message="form.errors.contract_types"
                        />
                    </fieldset>
                    <div class="mt-5 space-y-3 text-sm text-brand-green">
                        <label class="flex items-center gap-3">
                            <Checkbox v-model="form.flexible_hours" />
                            Elastyczne godziny pracy
                        </label>
                        <label class="flex items-center gap-3">
                            <Checkbox v-model="form.fixed_meeting_hours" />
                            Spotkania przed 15:00, bez wieczorów
                        </label>
                        <label class="flex items-center gap-3">
                            <Checkbox v-model="form.childcare_subsidy" />
                            Dofinansowanie żłobka lub przedszkola
                        </label>
                    </div>
                    <label
                        v-if="form.work_mode !== 'remote'"
                        :class="[labelClass, 'mt-5 block sm:max-w-xs']"
                    >
                        Żłobek / przedszkole w pobliżu (km)
                        <input
                            v-model.number="form.nursery_distance_km"
                            :aria-invalid="
                                form.errors.nursery_distance_km
                                    ? true
                                    : undefined
                            "
                            aria-describedby="nursery_distance_km-hint nursery_distance_km-error"
                            type="number"
                            min="0"
                            max="50"
                            step="1"
                            inputmode="numeric"
                            :class="fieldClass"
                            placeholder="2"
                            data-test="nursery-distance"
                        />
                        <span
                            id="nursery_distance_km-hint"
                            class="mt-1 block text-xs font-normal text-brand-green/80"
                        >
                            Podaj odległość od miejsca pracy – kandydatki mogą
                            filtrować ogłoszenia po tej informacji
                        </span>
                        <InputError
                            id="nursery_distance_km-error"
                            :message="form.errors.nursery_distance_km"
                        />
                    </label>
                    <div
                        class="mt-5 rounded-2xl bg-brand-mint-soft/60 p-4 text-sm text-brand-green"
                    >
                        <label class="flex items-center gap-3 font-semibold">
                            <Checkbox
                                v-model="form.is_job_share"
                                data-test="job-share-toggle"
                            />
                            Ogłoszenie dla wielu osób (job sharing)
                        </label>
                        <p class="mt-1 text-xs text-brand-green/80">
                            Jedno stanowisko, dwie osoby dzielące dzień pracy.
                            Kandydatki same dobiorą się w pary i zaproponują
                            podział godzin.
                        </p>
                        <div
                            v-if="form.is_job_share"
                            class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <label :class="labelClass">
                                Dzień pracy od
                                <input
                                    v-model="form.workday_starts_at"
                                    :aria-invalid="
                                        form.errors.workday_starts_at
                                            ? true
                                            : undefined
                                    "
                                    aria-describedby="workday_starts_at-error"
                                    type="time"
                                    step="1800"
                                    :class="fieldClass"
                                />
                                <InputError
                                    id="workday_starts_at-error"
                                    :message="form.errors.workday_starts_at"
                                />
                            </label>
                            <label :class="labelClass">
                                Dzień pracy do
                                <input
                                    v-model="form.workday_ends_at"
                                    :aria-invalid="
                                        form.errors.workday_ends_at
                                            ? true
                                            : undefined
                                    "
                                    aria-describedby="workday_ends_at-error"
                                    type="time"
                                    step="1800"
                                    :class="fieldClass"
                                />
                                <InputError
                                    id="workday_ends_at-error"
                                    :message="form.errors.workday_ends_at"
                                />
                            </label>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-green">
                        Czego szukasz
                    </h2>
                    <p class="mt-1 text-sm text-brand-green/80">
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
                                id="required_skills-error"
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
                                id="nice_to_have_skills-error"
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
