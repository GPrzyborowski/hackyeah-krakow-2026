<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import { Lock } from "@lucide/vue";
import { watch } from "vue";
import BrandSwitch from "@/components/candidate/BrandSwitch.vue";
import type {
    OnboardingProfile,
    Option,
    PreviewData,
} from "@/components/candidate/types";
import InputError from "@/components/InputError.vue";
import { preferences, show } from "@/routes/candidate/onboarding";
import { privacy } from "@/routes/public/legal";

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
    headline: props.profile.headline ?? "",
    years_of_experience: props.profile.years_of_experience as number | null,
    city: props.profile.city ?? "",
    work_modes: [...props.profile.work_modes],
    employment_fractions: [...props.profile.employment_fractions],
    wants_flexible_hours: props.profile.wants_flexible_hours,
    open_to_job_sharing: props.profile.open_to_job_sharing,
    preferred_day_part: (props.profile.preferred_day_part ?? "any") as
        "morning" | "afternoon" | "any",
    available_from: props.profile.available_from ?? "",
    leave_starts_on: props.profile.leave_starts_on ?? "",
    due_date: props.profile.due_date ?? "",
});

watch(
    () => [form.headline, form.years_of_experience, form.available_from],
    () =>
        emit("preview", {
            headline: form.headline || null,
            years_of_experience:
                form.years_of_experience === null ||
                String(form.years_of_experience) === ""
                    ? null
                    : Number(form.years_of_experience),
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
        years_of_experience:
            data.years_of_experience === null ||
            String(data.years_of_experience) === ""
                ? null
                : data.years_of_experience,
        leave_starts_on: data.leave_starts_on || null,
        due_date: data.due_date || null,
    })).put(props.submitUrl ?? preferences.url(), {
        preserveScroll: true,
        onSuccess: () => emit("saved"),
    });
}

const dayParts: Option[] = [
    { value: "morning", label: "Poranki" },
    { value: "afternoon", label: "Popołudnia" },
    { value: "any", label: "Bez znaczenia" },
];

const inputClass =
    "mt-1 w-full rounded-2xl border border-brand-line bg-white px-3 py-2 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40";
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

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
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
            <div>
                <label
                    for="years_of_experience"
                    class="text-sm font-semibold text-brand-green"
                    >Lata doświadczenia</label
                >
                <input
                    id="years_of_experience"
                    :aria-invalid="
                        form.errors.years_of_experience ? true : undefined
                    "
                    aria-describedby="years_of_experience-error"
                    v-model="form.years_of_experience"
                    type="number"
                    min="0"
                    max="50"
                    :class="inputClass"
                />
                <InputError
                    id="years_of_experience-error"
                    :message="form.errors.years_of_experience"
                />
            </div>
            <div>
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
                >Od kiedy możesz zacząć?</label
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

        <div class="mt-4 rounded-2xl border border-brand-mint-soft p-4">
            <h3 class="flex items-center gap-2 font-bold text-brand-green">
                <Lock class="size-4" /> Twój kalendarz powrotu
                <span class="text-xs font-normal text-brand-green/80"
                    >(opcjonalnie)</span
                >
            </h3>
            <p class="mt-1 text-xs text-brand-green/80">
                Dane o ciąży są opcjonalne i prywatne. Nigdy nie pokazujemy ich
                pracodawcom – służą tylko Twojemu kalendarzowi i przypomnieniom.
                Podając je, wyrażasz zgodę na ich przetwarzanie (art. 9 RODO) –
                możesz ją wycofać, usuwając daty.
                <a
                    :href="privacy.url()"
                    target="_blank"
                    rel="noopener"
                    class="font-semibold underline underline-offset-2"
                    >Polityka prywatności</a
                >
            </p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label
                        for="due_date"
                        class="text-sm font-semibold text-brand-green"
                        >Termin porodu</label
                    >
                    <input
                        id="due_date"
                        :aria-invalid="form.errors.due_date ? true : undefined"
                        aria-describedby="due_date-error"
                        v-model="form.due_date"
                        type="date"
                        :class="inputClass"
                    />
                    <InputError
                        id="due_date-error"
                        :message="form.errors.due_date"
                    />
                </div>
                <div>
                    <label
                        for="leave_starts_on"
                        class="text-sm font-semibold text-brand-green"
                        >Początek urlopu</label
                    >
                    <input
                        id="leave_starts_on"
                        :aria-invalid="
                            form.errors.leave_starts_on ? true : undefined
                        "
                        aria-describedby="leave_starts_on-error"
                        v-model="form.leave_starts_on"
                        type="date"
                        :class="inputClass"
                    />
                    <InputError
                        id="leave_starts_on-error"
                        :message="form.errors.leave_starts_on"
                    />
                </div>
            </div>
        </div>

        <div class="mt-8 flex items-center justify-between gap-3">
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
                {{ profile.is_published ? "Zapisz" : "Zapisz i przejdź dalej" }}
            </button>
        </div>
    </form>
</template>
