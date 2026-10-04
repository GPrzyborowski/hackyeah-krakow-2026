<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, FileText, Pencil, Sparkles } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import CandidateAvatar from '@/components/candidate/CandidateAvatar.vue';
import {
    formatFileSize,
    formatShortDate,
    pluralize,
} from '@/components/candidate/format';
import ContactDetailsCard from '@/components/candidate/onboarding/ContactDetailsCard.vue';
import PreferencesStep from '@/components/candidate/onboarding/PreferencesStep.vue';
import PrivacySettings from '@/components/candidate/onboarding/PrivacySettings.vue';
import ProfilePreviewCard from '@/components/candidate/onboarding/ProfilePreviewCard.vue';
import SkillTagsEditor from '@/components/candidate/profile/SkillTagsEditor.vue';
import type {
    OnboardingProfile,
    Option,
    ProfileSkill,
    ReturnCalendar,
} from '@/components/candidate/types';
import InputError from '@/components/InputError.vue';
import { cvAnalysis, profile as profileRoute } from '@/routes/candidate';
import { show, summary, visibility } from '@/routes/candidate/onboarding';
import { preferences } from '@/routes/candidate/profile';

const props = defineProps<{
    fullName: string;
    profile: OnboardingProfile;
    skills: ProfileSkill[];
    calendar: ReturnCalendar;
    skillSuggestions: string[];
    companies: { id: number; name: string }[];
    workModes: Option[];
    employmentFractions: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profil', href: profileRoute() }],
    },
});

const SUMMARY_MAX_LENGTH = 400;

// Profiles from before the stage choice open the editor at once, so the candidate can pick it.
const editingPreferences = ref(props.profile.stage === null);
const preferencesSection = ref<HTMLElement | null>(null);
const togglingVisibility = ref(false);

const summaryForm = useForm<{ ai_summary: string }>({
    ai_summary: props.profile.ai_summary ?? '',
});

watch(
    () => props.profile.ai_summary,
    (value) => {
        if (!summaryForm.isDirty) {
            summaryForm.defaults({ ai_summary: value ?? '' });
            summaryForm.reset();
        }
    },
);

const confirmedSkillNames = computed(() =>
    props.skills.filter((skill) => skill.confirmed).map((skill) => skill.name),
);

const subtitle = computed(() =>
    [
        props.profile.headline,
        props.profile.years_of_experience !== null
            ? `${props.profile.years_of_experience} ${pluralize(props.profile.years_of_experience, 'rok', 'lata', 'lat')} doświadczenia`
            : null,
        props.profile.city,
    ]
        .filter(Boolean)
        .join(' · '),
);

function labelsFor(values: string[], options: Option[]): string {
    const labels = options
        .filter((option) => values.includes(option.value))
        .map((option) => option.label);

    return labels.length ? labels.join(', ') : 'Nie wybrano';
}

const dayPartLabels: Record<string, string> = {
    morning: 'Poranki',
    afternoon: 'Popołudnia',
    any: 'Bez znaczenia',
};

const preferenceRows = computed(() => [
    {
        label: 'Gdzie teraz jesteś (widzisz tylko Ty)',
        value: props.profile.stage_label ?? 'Nie wybrano',
    },
    {
        label: 'Od kiedy możesz zacząć',
        value: props.profile.available_from
            ? formatShortDate(props.profile.available_from, true)
            : 'Nie podano',
    },
    {
        label: 'Tryb pracy',
        value: labelsFor(props.profile.work_modes, props.workModes),
    },
    {
        label: 'Wymiar etatu',
        value: labelsFor(
            props.profile.employment_fractions,
            props.employmentFractions,
        ),
    },
    {
        label: 'Pora dnia',
        value: dayPartLabels[props.profile.preferred_day_part ?? 'any'],
    },
    {
        label: 'Elastyczne godziny',
        value: props.profile.wants_flexible_hours ? 'Tak' : 'Nie',
    },
    {
        label: 'Job sharing',
        value: props.profile.open_to_job_sharing
            ? 'Otwarta na podział etatu'
            : 'Nie',
    },
]);

const cvStatusLabel = computed(() => {
    switch (props.profile.cv_status) {
        case 'parsed':
            return 'przeanalizowano';
        case 'parsing':
            return 'analizuję…';
        case 'failed':
            return 'nie udało się przeanalizować';
        default:
            return 'zapisano';
    }
});

function saveSummary() {
    summaryForm.patch(summary.url(), {
        preserveScroll: true,
        onSuccess: () => summaryForm.defaults(),
    });
}

async function editPreferences() {
    editingPreferences.value = true;
    await nextTick();
    preferencesSection.value?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function toggleVisibility() {
    router.visit(visibility(), {
        preserveScroll: true,
        onStart: () => (togglingVisibility.value = true),
        onFinish: () => (togglingVisibility.value = false),
    });
}
</script>

<template>
    <Head title="Mój profil" />

    <div
        class="mx-auto grid w-full max-w-6xl grid-cols-1 gap-6 p-4 md:p-8 xl:grid-cols-[minmax(0,1fr)_22rem]"
    >
        <div class="flex min-w-0 flex-col gap-6">
            <section
                class="flex flex-wrap items-center gap-5 rounded-3xl bg-white p-6 shadow-sm"
                aria-labelledby="profile-heading"
            >
                <CandidateAvatar
                    :name="fullName"
                    :photo-url="profile.photo_url"
                    size="lg"
                />
                <div class="min-w-0 flex-1 basis-48">
                    <h1
                        id="profile-heading"
                        class="text-3xl font-extrabold tracking-tight text-brand-green"
                    >
                        {{ fullName }}
                    </h1>
                    <p class="mt-1 text-sm text-brand-green/80">
                        {{ subtitle || 'Uzupełnij stanowisko i staż' }}
                    </p>
                    <p
                        class="mt-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                        :class="
                            profile.is_published
                                ? 'bg-brand-mint-soft text-brand-green'
                                : 'bg-brand-peach text-brand-green'
                        "
                    >
                        <component
                            :is="profile.is_published ? Eye : EyeOff"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{
                            profile.is_published
                                ? 'Profil widoczny dla pracodawców'
                                : 'Profil ukryty'
                        }}
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="togglingVisibility"
                    class="rounded-full border border-brand-green px-5 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-50"
                    @click="toggleVisibility"
                >
                    {{ profile.is_published ? 'Ukryj profil' : 'Pokaż profil' }}
                </button>
            </section>

            <section
                class="rounded-3xl bg-white p-6 shadow-sm"
                aria-labelledby="skills-heading"
            >
                <h2
                    id="skills-heading"
                    class="text-lg font-bold text-brand-green"
                >
                    Umiejętności
                </h2>
                <p class="mt-1 mb-4 text-sm text-brand-green/80">
                    Na ich podstawie dopasowujemy oferty. Tagi, które dodasz
                    sama, są od razu zatwierdzone.
                </p>
                <SkillTagsEditor
                    :skills="skills"
                    :skill-suggestions="skillSuggestions"
                />
            </section>

            <form
                class="rounded-3xl bg-white p-6 shadow-sm"
                @submit.prevent="saveSummary"
            >
                <label
                    for="profile_ai_summary"
                    class="flex items-center gap-2 text-lg font-bold text-brand-green"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    Opis dla pracodawców
                </label>
                <p class="mt-1 text-xs text-brand-green/80">
                    Krótki opis na Twoim anonimowym profilu. Nie wpisuj e-maila,
                    telefonu ani informacji o rodzinie.
                </p>
                <textarea
                    id="profile_ai_summary"
                    v-model="summaryForm.ai_summary"
                    :aria-invalid="
                        summaryForm.errors.ai_summary ? true : undefined
                    "
                    aria-describedby="profile_ai_summary-error"
                    rows="3"
                    :maxlength="SUMMARY_MAX_LENGTH"
                    placeholder="Np. Od 6 lat prowadzę rekrutacje IT i onboarding nowych osób."
                    class="mt-2 w-full rounded-2xl border border-brand-line p-3 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40"
                />
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <InputError
                        id="profile_ai_summary-error"
                        :message="summaryForm.errors.ai_summary"
                    />
                    <span class="ml-auto text-xs text-brand-green/80"
                        >{{ summaryForm.ai_summary.length }}/{{
                            SUMMARY_MAX_LENGTH
                        }}</span
                    >
                </div>
                <button
                    type="submit"
                    :disabled="summaryForm.processing || !summaryForm.isDirty"
                    class="mt-2 rounded-full border border-brand-green px-5 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-50"
                >
                    Zapisz opis
                </button>
            </form>

            <div ref="preferencesSection" class="scroll-mt-4">
                <PreferencesStep
                    v-if="editingPreferences"
                    :profile="profile"
                    :work-modes="workModes"
                    :employment-fractions="employmentFractions"
                    :submit-url="preferences.url()"
                    @cancel="editingPreferences = false"
                    @saved="editingPreferences = false"
                />
                <section
                    v-else
                    class="rounded-3xl bg-white p-6 shadow-sm"
                    aria-labelledby="preferences-heading"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2
                            id="preferences-heading"
                            class="text-lg font-bold text-brand-green"
                        >
                            Preferencje pracy
                        </h2>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border border-brand-green px-4 py-1.5 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                            @click="editPreferences"
                        >
                            <Pencil class="size-3.5" aria-hidden="true" />
                            Edytuj<span class="sr-only"> preferencje</span>
                        </button>
                    </div>
                    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div
                            v-for="row in preferenceRows"
                            :key="row.label"
                            class="rounded-2xl bg-brand-cream p-3"
                        >
                            <dt class="text-xs text-brand-green/80">
                                {{ row.label }}
                            </dt>
                            <dd class="font-semibold text-brand-green">
                                {{ row.value }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section
                class="rounded-3xl bg-white p-6 shadow-sm"
                aria-labelledby="cv-heading"
            >
                <h2 id="cv-heading" class="text-lg font-bold text-brand-green">
                    CV
                </h2>
                <div
                    v-if="profile.cv_original_name"
                    class="mt-3 flex items-center gap-3 rounded-2xl bg-brand-cream p-4"
                >
                    <FileText
                        class="size-6 shrink-0 text-brand-green"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-brand-green">
                            {{ profile.cv_original_name }}
                        </p>
                        <p class="text-xs text-brand-green/80">
                            <template v-if="profile.cv_size"
                                >{{ formatFileSize(profile.cv_size) }} ·
                            </template>
                            {{ cvStatusLabel }}
                        </p>
                    </div>
                </div>
                <p v-else class="mt-2 text-sm text-brand-green/80">
                    Nie dodałaś jeszcze CV. Asystent przeczyta je i zaproponuje
                    umiejętności.
                </p>
                <p class="mt-2 text-xs text-brand-green/80">
                    Pracodawcy nigdy nie widzą pliku CV.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        :href="show({ query: { step: 2 } })"
                        class="rounded-full bg-brand-green px-5 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft"
                    >
                        {{
                            profile.cv_original_name
                                ? 'Wgraj nowe CV'
                                : 'Dodaj CV'
                        }}
                    </Link>
                    <Link
                        v-if="profile.cv_status === 'parsed'"
                        :href="cvAnalysis()"
                        class="rounded-full border border-brand-green px-5 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream"
                    >
                        Zobacz analizę CV
                    </Link>
                </div>
            </section>
        </div>

        <aside class="flex flex-col gap-6" aria-label="Widoczność i prywatność">
            <ProfilePreviewCard
                :anonymous-name="profile.anonymous_name"
                :headline="profile.headline"
                :years-of-experience="profile.years_of_experience"
                :summary="profile.ai_summary"
                :skills="confirmedSkillNames"
                :available-from="profile.available_from"
            />
            <ContactDetailsCard
                :name="fullName"
                :phone="profile.phone"
                :photo-url="profile.photo_url"
            />
            <PrivacySettings
                :hidden-from-company-id="profile.hidden_from_company_id"
                :allow-direct-messages="profile.allow_direct_messages"
                :job-alerts-enabled="profile.job_alerts_enabled"
                :show-availability-instead-of-gap="
                    profile.show_availability_instead_of_gap
                "
                :career-gap-note="profile.career_gap_note"
                :companies="companies"
            />
        </aside>
    </div>
</template>
