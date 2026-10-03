<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, EyeOff, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatShortDate } from '@/components/candidate/format';
import PrivacySettings from '@/components/candidate/onboarding/PrivacySettings.vue';
import type {
    OnboardingProfile,
    ProfileSkill,
} from '@/components/candidate/types';
import InputError from '@/components/InputError.vue';
import { publish, show, visibility } from '@/routes/candidate/onboarding';

const props = defineProps<{
    profile: OnboardingProfile;
    skills: ProfileSkill[];
    companies: { id: number; name: string }[];
}>();

const page = usePage();
const processing = ref(false);
const errors = computed(() => page.props.errors as Record<string, string>);
const confirmedSkillsCount = computed(
    () => props.skills.filter((skill) => skill.confirmed).length,
);

const checklist = computed(() => [
    {
        done: confirmedSkillsCount.value > 0,
        label:
            confirmedSkillsCount.value > 0
                ? `Zatwierdzone umiejętności: ${confirmedSkillsCount.value}`
                : 'Zatwierdź przynajmniej jedną umiejętność',
        step: 2,
    },
    {
        done: props.profile.available_from !== null,
        label: props.profile.available_from
            ? `Gotowa od ${formatShortDate(props.profile.available_from, true)}`
            : 'Podaj, od kiedy możesz zacząć',
        step: 3,
    },
]);

function submit(action: 'publish' | 'visibility') {
    router.visit(action === 'publish' ? publish() : visibility(), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-3xl bg-white p-6 shadow-sm md:p-8">
            <h2 class="text-3xl font-extrabold tracking-tight text-brand-green">
                {{
                    profile.is_published
                        ? 'Widoczność profilu'
                        : 'Prywatność i publikacja'
                }}
            </h2>
            <p class="mt-2 text-sm text-brand-green/70">
                Firmy zobaczą Twoje imię z inicjałem nazwiska, stanowisko,
                zatwierdzone umiejętności i datę dostępności. Nazwisko i e-mail
                poznają dopiero po przyjęciu zaproszenia.
            </p>

            <ul class="mt-6 space-y-2">
                <li
                    v-for="item in checklist"
                    :key="item.step"
                    class="flex items-center gap-3 text-sm text-brand-green"
                >
                    <span
                        class="flex size-6 items-center justify-center rounded-full"
                        :class="
                            item.done
                                ? 'bg-brand-green text-white'
                                : 'bg-brand-peach text-brand-green'
                        "
                    >
                        <Check v-if="item.done" class="size-3.5" />
                        <X v-else class="size-3.5" />
                    </span>
                    <span class="flex-1">{{ item.label }}</span>
                    <Link
                        v-if="!item.done"
                        :href="show({ query: { step: item.step } })"
                        class="text-xs font-semibold underline underline-offset-4"
                    >
                        Uzupełnij
                    </Link>
                </li>
            </ul>
            <InputError class="mt-2" :message="errors.available_from" />
            <InputError class="mt-1" :message="errors.skills" />

            <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="show({ query: { step: 3 } })"
                    class="rounded-full border border-brand-green px-5 py-2.5 text-sm font-semibold text-brand-green"
                >
                    Wstecz
                </Link>
                <button
                    v-if="!profile.is_published"
                    type="button"
                    :disabled="processing"
                    class="rounded-full bg-brand-green px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                    @click="submit('publish')"
                >
                    Opublikuj profil
                </button>
                <button
                    v-else
                    type="button"
                    :disabled="processing"
                    class="inline-flex items-center gap-2 rounded-full border border-brand-green px-6 py-2.5 text-sm font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-50"
                    @click="submit('visibility')"
                >
                    <EyeOff class="size-4" /> Ukryj profil
                </button>
            </div>
        </div>

        <PrivacySettings
            :show-availability-instead-of-gap="
                profile.show_availability_instead_of_gap
            "
            :allow-direct-messages="profile.allow_direct_messages"
            :hidden-from-company-id="profile.hidden_from_company_id"
            :companies="companies"
        />
    </div>
</template>
