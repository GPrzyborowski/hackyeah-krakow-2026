<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Baby, Briefcase, Lock } from '@lucide/vue';
import { watch } from 'vue';
import BrandSwitch from '@/components/candidate/BrandSwitch.vue';
import type {
    CandidateStage,
    OnboardingProfile,
    Option,
    PreviewData,
} from '@/components/candidate/types';
import InputError from '@/components/InputError.vue';
import { preferences, show } from '@/routes/candidate/onboarding';

const props = defineProps<{
    profile: OnboardingProfile;
    workModes: Option[];
    employmentFractions: Option[];
    /**
     * Alternative endpoint used outside the wizard (the profile page); shows "Anuluj" instead of "Wstecz".
     */
    submitUrl?: string;
}>();

const emit = defineEmits<{
    preview: [data: PreviewData];
    cancel: [];
    saved: [];
}>();

const form = useForm({
    stage: props.profile.stage as CandidateStage | null,
    headline: props.profile.headline ?? '',
    city: props.profile.city ?? '',
    work_modes: [...props.profile.work_modes],
    employment_fractions: [...props.profile.employment_fractions],
    wants_flexible_hours: props.profile.wants_flexible_hours,
    open_to_job_sharing: props.profile.open_to_job_sharing,
    preferred_day_part: (props.profile.preferred_day_part ?? 'any') as
        | 'morning'
        | 'afternoon'
        | 'any',
    available_from: props.profile.available_from ?? '',
    leave_starts_on: props.profile.leave_starts_on ?? '',
    due_date: props.profile.due_date ?? '',
});

watch(
    () => [form.headline, form.available_from],
    () =>
        emit('preview', {
            headline: form.headline || null,
            available_from: form.available_from || null,
        }),
);

function toggleValue(list: string[], value: string) {
    const position = list.indexOf(value);

    if (position === -1) {
        list.push(value);
    } else {
        list.splice(position, 1);
    }
}

function submit() {
    form.transform((data) => ({
        ...data,
        leave_starts_on: data.leave_starts_on || null,
        due_date: data.stage === 'pregnant' ? data.due_date || null : null,
    })).put(props.submitUrl ?? preferences.url(), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}

const stageChoices: {
    value: CandidateStage;
    title: string;
    description: string;
    icon: typeof Baby;
}[] = [
    {
        value: 'pregnant',
        title: 'Jestem w ciąży',
        description: 'Planuję powrót do pracy po porodzie i urlopie.',
        icon: Baby,
    },
    {
        value: 'after_leave',
        title: 'Jestem po urlopie macierzyńskim (lub na nim)',
        description: 'Wracam do pracy albo przygotowuję się do powrotu.',
        icon: Briefcase,
    },
];

const dayParts: Option[] = [
    { value: 'morning', label: 'Poranki' },
    { value: 'afternoon', label: 'Popołudnia' },
    { value: 'any', label: 'Bez znaczenia' },
];

const inputClass =
    'mt-1 w-full rounded-2xl border border-brand-line bg-white px-3 py-2 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40';
</script>

<template>
    <form
        class="rounded-3xl bg-white p-6 shadow-sm md:p-8"
        @submit.prevent="submit"
    >
        <h2 class="text-3xl font-extrabold tracking-tight text-brand-green">
            Preferencje pracy
        </h2>
        <p class="mt-2 text-sm text-brand-green/80">
            Na tej podstawie dopasujemy oferty i pokażemy Cię właściwym firmom.
        </p>

        <fieldset class="mt-6" aria-describedby="stage-note stage-error">
            <legend class="text-base font-bold text-brand-green">
                Gdzie teraz jesteś?
            </legend>
            <p
                id="stage-note"
                class="mt-1 flex items-center gap-1.5 text-xs text-brand-green/80"
            >
                <Lock class="size-3 shrink-0" aria-hidden="true" />
                Widzisz to tylko Ty.
            </p>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label
                    v-for="choice in stageChoices"
                    :key="choice.value"
                    class="flex cursor-pointer items-start gap-3 rounded-3xl border-2 p-4 transition-colors focus-within:ring-2 focus-within:ring-brand-green/40"
                    :class="
                        form.stage === choice.value
                            ? 'border-brand-green bg-brand-mint-soft'
                            : 'border-brand-line bg-white hover:bg-brand-cream'
                    "
                    :data-test="`stage-${choice.value}`"
                >
                    <input
                        v-model="form.stage"
                        type="radio"
                        name="stage"
                        :value="choice.value"
                        required
                        class="sr-only"
                    />
                    <component
                        :is="choice.icon"
                        class="mt-0.5 size-6 shrink-0 text-brand-green"
                        aria-hidden="true"
                    />
                    <span>
                        <span class="block font-bold text-brand-green">{{
                            choice.title
                        }}</span>
                        <span class="block text-xs text-brand-green/80">{{
                            choice.description
                        }}</span>
                    </span>
                </label>
            </div>
            <InputError id="stage-error" :message="form.errors.stage" />
        </fieldset>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label
                    for="headline"
                    class="text-sm font-semibold text-brand-green"
                    >Stanowisko / specjalizacja</label
                >
                <input
                    id="headline"
                    :aria-invalid="form.errors.headline ? true : undefined"
                    aria-describedby="headline-error"
                    v-model="form.headline"
                    type="text"
                    placeholder="np. Specjalistka ds. rekrutacji"
                    :class="inputClass"
                />
                <InputError
                    id="headline-error"
                    :message="form.errors.headline"
                />
            </div>
            <div class="sm:col-span-2">
                <label for="city" class="text-sm font-semibold text-brand-green"
                    >Miasto</label
                >
                <input
                    id="city"
                    :aria-invalid="form.errors.city ? true : undefined"
                    aria-describedby="city-error"
                    v-model="form.city"
                    type="text"
                    placeholder="np. Poznań"
                    :class="inputClass"
                />
                <InputError id="city-error" :message="form.errors.city" />
            </div>
        </div>

        <fieldset class="mt-6">
            <legend class="text-sm font-semibold text-brand-green">
                Tryb pracy
            </legend>
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="mode in workModes"
                    :key="mode.value"
                    type="button"
                    :aria-pressed="form.work_modes.includes(mode.value)"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        form.work_modes.includes(mode.value)
                            ? 'bg-brand-green text-white'
                            : 'bg-brand-cream text-brand-green hover:bg-brand-mint-soft'
                    "
                    @click="toggleValue(form.work_modes, mode.value)"
                >
                    {{ mode.label }}
                </button>
            </div>
        </fieldset>

        <fieldset class="mt-5">
            <legend class="text-sm font-semibold text-brand-green">
                Wymiar etatu
            </legend>
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="fraction in employmentFractions"
                    :key="fraction.value"
                    type="button"
                    :aria-pressed="
                        form.employment_fractions.includes(fraction.value)
                    "
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        form.employment_fractions.includes(fraction.value)
                            ? 'bg-brand-green text-white'
                            : 'bg-brand-cream text-brand-green hover:bg-brand-mint-soft'
                    "
                    @click="
                        toggleValue(form.employment_fractions, fraction.value)
                    "
                >
                    {{ fraction.label }}
                </button>
            </div>
        </fieldset>

        <div class="mt-4 divide-y divide-brand-cream">
            <BrandSwitch
                v-model="form.wants_flexible_hours"
                label="Zależy mi na elastycznych godzinach"
            />
            <BrandSwitch
                v-model="form.open_to_job_sharing"
                label="Jestem otwarta na job sharing"
            />
            <div v-if="form.open_to_job_sharing" class="py-3">
                <label
                    for="preferred_day_part"
                    class="text-sm font-semibold text-brand-green"
                    >Którą część dnia wolisz w parze?</label
                >
                <select
                    id="preferred_day_part"
                    :aria-invalid="
                        form.errors.preferred_day_part ? true : undefined
                    "
                    aria-describedby="preferred_day_part-error"
                    v-model="form.preferred_day_part"
                    :class="inputClass"
                >
                    <option
                        v-for="dayPart in dayParts"
                        :key="dayPart.value"
                        :value="dayPart.value"
                    >
                        {{ dayPart.label }}
                    </option>
                </select>
                <p class="mt-1 text-xs text-brand-green/80">
                    Pomożemy dobrać partnerkę, która woli drugą połowę dnia.
                </p>
                <InputError
                    id="preferred_day_part-error"
                    :message="form.errors.preferred_day_part"
                />
            </div>
        </div>

        <div class="mt-4 rounded-2xl bg-brand-yellow/40 p-4">
            <label
                for="available_from"
                class="text-base font-bold text-brand-green"
                >{{
                    form.stage === 'after_leave'
                        ? 'Kiedy kończysz urlop – od kiedy możesz zacząć?'
                        : 'Od kiedy możesz zacząć?'
                }}</label
            >
            <input
                id="available_from"
                :aria-invalid="form.errors.available_from ? true : undefined"
                aria-describedby="available_from-error"
                v-model="form.available_from"
                type="date"
                required
                :class="inputClass"
            />
            <p class="mt-1 text-xs text-brand-green/80">
                Pracodawcy zobaczą tylko tę datę – „Dostępna od”.
            </p>
            <InputError
                id="available_from-error"
                :message="form.errors.available_from"
            />
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <button
                v-if="submitUrl"
                type="button"
                class="shrink-0 rounded-full border border-brand-green px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-brand-green hover:bg-brand-cream lg:px-4 xl:px-5"
                @click="emit('cancel')"
            >
                Anuluj
            </button>
            <Link
                v-else
                :href="show({ query: { step: 2 } })"
                class="shrink-0 rounded-full border border-brand-green px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-brand-green lg:px-4 xl:px-5"
            >
                Wstecz
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="shrink-0 rounded-full bg-brand-green px-6 py-2.5 text-sm font-semibold whitespace-nowrap text-white hover:bg-brand-green-soft disabled:cursor-not-allowed disabled:bg-brand-green/40 disabled:hover:bg-brand-green/40 lg:px-4 xl:px-5"
            >
                {{ profile.is_published ? 'Zapisz' : 'Zapisz i przejdź dalej' }}
            </button>
        </div>
    </form>
</template>
