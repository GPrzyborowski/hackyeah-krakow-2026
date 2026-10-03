<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye, EyeOff } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CvStep from '@/components/candidate/onboarding/CvStep.vue';
import OnboardingStepper from '@/components/candidate/onboarding/OnboardingStepper.vue';
import PreferencesStep from '@/components/candidate/onboarding/PreferencesStep.vue';
import PrivacySettings from '@/components/candidate/onboarding/PrivacySettings.vue';
import ProfilePreviewCard from '@/components/candidate/onboarding/ProfilePreviewCard.vue';
import PublishStep from '@/components/candidate/onboarding/PublishStep.vue';
import type {
    OnboardingProfile,
    Option,
    PreviewData,
    ProfileSkill,
} from '@/components/candidate/types';
import { show, visibility } from '@/routes/candidate/onboarding';

const props = defineProps<{
    step: number;
    profile: OnboardingProfile;
    skills: ProfileSkill[];
    skillSuggestions: string[];
    companies: { id: number; name: string }[];
    workModes: Option[];
    employmentFractions: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profil', href: show() }],
    },
});

const previewOverrides = ref<PreviewData | null>(null);

watch(
    () => props.step,
    () => (previewOverrides.value = null),
);

const preview = computed<PreviewData>(
    () =>
        previewOverrides.value ?? {
            headline: props.profile.headline,
            years_of_experience: props.profile.years_of_experience,
            available_from: props.profile.available_from,
        },
);

const isProfileFinished = computed(() => props.profile.onboarding_step >= 4);

function toggleVisibility() {
    router.visit(visibility(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="profile.is_published ? 'Profil' : 'Stwórz profil'" />

    <div class="grid gap-6 p-4 md:p-8 xl:grid-cols-[14rem_minmax(0,1fr)_20rem]">
        <aside class="flex flex-col gap-4">
            <OnboardingStepper
                :step="step"
                :completed-step="profile.onboarding_step"
                :is-published="profile.is_published"
            />
            <div
                v-if="isProfileFinished"
                class="rounded-3xl bg-white p-4 text-sm text-brand-green shadow-sm"
            >
                <p class="flex items-center gap-2 font-semibold">
                    <component
                        :is="profile.is_published ? Eye : EyeOff"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{
                        profile.is_published
                            ? 'Profil jest widoczny'
                            : 'Profil jest ukryty'
                    }}
                </p>
                <button
                    type="button"
                    class="mt-3 w-full rounded-full border border-brand-green px-4 py-2 text-sm font-semibold hover:bg-brand-cream"
                    @click="toggleVisibility"
                >
                    {{ profile.is_published ? 'Ukryj profil' : 'Pokaż profil' }}
                </button>
            </div>
        </aside>

        <section class="min-w-0" aria-label="Bieżący krok">
            <CvStep
                v-if="step === 2"
                :profile="profile"
                :skills="skills"
                :skill-suggestions="skillSuggestions"
            />
            <PreferencesStep
                v-else-if="step === 3"
                :profile="profile"
                :work-modes="workModes"
                :employment-fractions="employmentFractions"
                @preview="(data) => (previewOverrides = data)"
            />
            <PublishStep
                v-else
                :profile="profile"
                :skills="skills"
                :companies="companies"
            />
        </section>

        <aside class="flex flex-col gap-4">
            <ProfilePreviewCard
                :anonymous-name="profile.anonymous_name"
                :headline="preview.headline"
                :years-of-experience="preview.years_of_experience"
                :summary="profile.ai_summary"
                :skills="skills.map((skill) => skill.name)"
                :available-from="preview.available_from"
            />
            <PrivacySettings
                v-if="step !== 4"
                :hidden-from-company-id="profile.hidden_from_company_id"
                :allow-direct-messages="profile.allow_direct_messages"
                :companies="companies"
            />
        </aside>
    </div>
</template>
