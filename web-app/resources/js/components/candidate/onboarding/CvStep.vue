<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Info, Plus, Sparkles, Upload, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { formatFileSize } from '@/components/candidate/format';
import type {
    OnboardingProfile,
    ProfileSkill,
} from '@/components/candidate/types';
import InputError from '@/components/InputError.vue';
import { home } from '@/routes/candidate';
import { cv, summary } from '@/routes/candidate/onboarding';
import { confirm } from '@/routes/candidate/onboarding/skills';
import { destroy, store } from '@/routes/candidate/skills';

const props = defineProps<{
    profile: OnboardingProfile;
    skills: ProfileSkill[];
    skillSuggestions: string[];
}>();

const page = usePage();

const form = useForm<{ cv: File | null; cv_text: string }>({
    cv: null,
    cv_text: props.profile.cv_text ?? '',
});

const SUMMARY_MAX_LENGTH = 400;

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

const fileInput = ref<HTMLInputElement | null>(null);
const showTextarea = ref(!props.profile.cv_original_name);
const isAddingTag = ref(false);
const newTag = ref('');
const tagInput = ref<HTMLInputElement | null>(null);
const confirming = ref(false);

const statusLabel = computed(() => {
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

const unusedSuggestions = computed(() => {
    const taken = new Set(
        props.skills.map((skill) => skill.name.toLowerCase()),
    );

    return props.skillSuggestions.filter(
        (name) => !taken.has(name.toLowerCase()),
    );
});

const skillsError = computed(
    () => (page.props.errors as Record<string, string>)?.skills,
);

function pickFile(event: Event) {
    const target = event.target as HTMLInputElement;
    form.cv = target.files?.[0] ?? null;
}

function analyze() {
    form.post(cv.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.cv = null;

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function saveSummary() {
    summaryForm.patch(summary.url(), {
        preserveScroll: true,
        onSuccess: () => summaryForm.defaults(),
    });
}

function removeSkill(skill: ProfileSkill) {
    router.visit(destroy(skill.id), { preserveScroll: true });
}

async function openTagInput() {
    isAddingTag.value = true;
    await nextTick();
    tagInput.value?.focus();
}

function addTag() {
    const name = newTag.value.trim();

    if (name.length < 2) {
        return;
    }

    router.post(
        store.url(),
        { name },
        {
            preserveScroll: true,
            onSuccess: () => {
                newTag.value = '';
                tagInput.value?.focus();
            },
        },
    );
}

function confirmAndContinue() {
    router.visit(confirm(), {
        onStart: () => (confirming.value = true),
        onFinish: () => (confirming.value = false),
    });
}
</script>

<template>
    <div class="rounded-3xl bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-3xl font-extrabold tracking-tight text-brand-green">
            {{ skills.length ? 'Przeczytaliśmy Twoje CV' : 'Dodaj CV' }}
        </h2>
        <p class="mt-2 text-sm text-brand-green/70">
            Asystent przeczyta plik, zaproponuje tagi i stanowiska. Nic nie
            trafi do pracodawców, dopóki tego nie zatwierdzisz.
        </p>

        <form class="mt-6 space-y-3" @submit.prevent="analyze">
            <div
                v-if="profile.cv_original_name && !form.cv"
                class="flex items-center gap-3 rounded-2xl bg-brand-cream p-4"
            >
                <FileText class="size-6 shrink-0 text-brand-green" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-brand-green">
                        {{ profile.cv_original_name }}
                    </p>
                    <p class="text-xs text-brand-green/60">
                        <template v-if="profile.cv_size"
                            >{{ formatFileSize(profile.cv_size) }} ·
                        </template>
                        {{ statusLabel }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-full border border-brand-green px-4 py-1.5 text-sm font-semibold text-brand-green hover:bg-white"
                    @click="fileInput?.click()"
                >
                    Zmień plik
                </button>
            </div>

            <label
                v-else
                class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-brand-mint p-6 text-center text-sm text-brand-green hover:bg-brand-cream"
            >
                <Upload class="size-6" />
                <span class="font-semibold">
                    {{ form.cv ? form.cv.name : 'Wybierz plik PDF z CV' }}
                </span>
                <span class="text-xs text-brand-green/60">maks. 5 MB</span>
                <input
                    ref="fileInput"
                    type="file"
                    accept="application/pdf,.pdf"
                    class="sr-only"
                    @change="pickFile"
                />
            </label>
            <input
                v-if="profile.cv_original_name && !form.cv"
                ref="fileInput"
                type="file"
                accept="application/pdf,.pdf"
                class="sr-only"
                @change="pickFile"
            />
            <InputError :message="form.errors.cv" />

            <button
                v-if="!showTextarea"
                type="button"
                class="text-sm font-semibold text-brand-green underline underline-offset-4"
                @click="showTextarea = true"
            >
                Wklej treść CV
            </button>
            <div v-else>
                <label
                    for="cv_text"
                    class="text-sm font-semibold text-brand-green"
                    >Wklej treść CV</label
                >
                <textarea
                    id="cv_text"
                    v-model="form.cv_text"
                    rows="5"
                    placeholder="Doświadczenie, obowiązki, narzędzia…"
                    class="mt-1 w-full rounded-2xl border border-brand-mint-soft p-3 text-sm text-brand-green outline-none focus:border-brand-mint"
                />
                <InputError :message="form.errors.cv_text" />
            </div>

            <button
                type="submit"
                :disabled="
                    form.processing || (!form.cv && !form.cv_text.trim())
                "
                class="inline-flex items-center gap-2 rounded-full bg-brand-peach px-5 py-2 text-sm font-semibold text-brand-green disabled:opacity-50"
            >
                <Sparkles class="size-4" />
                {{ form.processing ? 'Analizuję CV…' : 'Przeanalizuj CV' }}
            </button>
        </form>

        <div
            class="mt-4 flex items-start gap-2 rounded-2xl bg-brand-mint-soft/60 p-3 text-xs text-brand-green"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>
                Tagi to propozycje. Usuń te, które nie pasują, i dodaj własne
                przyciskiem „+ Dodaj tag” – nie musisz czekać na analizę.
            </p>
        </div>

        <h3 class="mt-8 text-lg font-bold text-brand-green">
            Umiejętności, które znaleźliśmy
        </h3>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span
                v-for="skill in skills"
                :key="skill.id"
                class="inline-flex items-center gap-1 rounded-full bg-brand-green py-1 pr-1.5 pl-3 text-xs font-medium text-white"
                :class="{ 'ring-2 ring-brand-yellow': !skill.confirmed }"
                :title="skill.confirmed ? 'Zatwierdzona' : 'Do zatwierdzenia'"
            >
                <Sparkles
                    v-if="skill.source === 'ai' && !skill.confirmed"
                    class="size-3 text-brand-yellow"
                />
                {{ skill.name }}
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-white/20"
                    :aria-label="`Usuń ${skill.name}`"
                    @click="removeSkill(skill)"
                >
                    <X class="size-3" />
                </button>
            </span>

            <form
                v-if="isAddingTag"
                class="inline-flex items-center gap-1"
                @submit.prevent="addTag"
            >
                <input
                    ref="tagInput"
                    v-model="newTag"
                    list="skill-suggestions"
                    placeholder="np. Excel"
                    aria-label="Nowa umiejętność"
                    class="w-40 rounded-full border border-brand-green/30 px-3 py-1 text-xs text-brand-green outline-none focus:border-brand-green"
                    @keydown.esc="isAddingTag = false"
                />
                <datalist id="skill-suggestions">
                    <option
                        v-for="name in unusedSuggestions"
                        :key="name"
                        :value="name"
                    />
                </datalist>
                <button
                    type="submit"
                    class="rounded-full bg-brand-yellow px-3 py-1 text-xs font-semibold text-brand-green"
                >
                    Dodaj
                </button>
            </form>
            <button
                v-else
                type="button"
                class="inline-flex items-center gap-1 rounded-full border border-brand-green/30 px-3 py-1 text-xs font-medium text-brand-green hover:bg-brand-cream"
                @click="openTagInput"
            >
                <Plus class="size-3" /> Dodaj tag
            </button>
        </div>
        <p v-if="skills.length === 0" class="mt-2 text-xs text-brand-green/60">
            Jeszcze nic tu nie ma. Przeanalizuj CV albo dodaj tagi ręcznie.
        </p>
        <InputError class="mt-2" :message="skillsError" />

        <form class="mt-8" @submit.prevent="saveSummary">
            <label for="ai_summary" class="text-lg font-bold text-brand-green"
                >To zobaczą pracodawcy</label
            >
            <p class="mt-1 text-xs text-brand-green/60">
                Krótki opis na Twoim anonimowym profilu. Popraw go po swojemu –
                bez e-maila, telefonu i informacji o rodzinie.
            </p>
            <textarea
                id="ai_summary"
                v-model="summaryForm.ai_summary"
                rows="3"
                :maxlength="SUMMARY_MAX_LENGTH"
                placeholder="Np. Od 6 lat prowadzę rekrutacje IT i onboarding nowych osób."
                class="mt-2 w-full rounded-2xl border border-brand-mint-soft p-3 text-sm text-brand-green outline-none focus:border-brand-mint"
            />
            <div class="flex flex-wrap items-center justify-between gap-2">
                <InputError :message="summaryForm.errors.ai_summary" />
                <span class="ml-auto text-xs text-brand-green/60"
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

        <h3 class="mt-8 text-lg font-bold text-brand-green">
            Stanowiska, które do Ciebie pasują
        </h3>
        <div
            v-if="profile.suggested_positions.length"
            class="mt-3 grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-3"
        >
            <div
                v-for="(
                    position, position_index
                ) in profile.suggested_positions"
                :key="position.title"
                class="rounded-2xl p-4 text-brand-green"
                :class="
                    position_index < 2 ? 'bg-brand-yellow' : 'bg-brand-cream'
                "
            >
                <p class="font-semibold break-words hyphens-auto" lang="pl">
                    {{ position.title }}
                </p>
                <p class="text-xs">pasuje w {{ position.score }}%</p>
            </div>
        </div>
        <p v-else class="mt-2 text-sm text-brand-green/60">
            Propozycje stanowisk pojawią się, gdy asystent AI przeanalizuje
            Twoje CV.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <Link
                v-if="profile.is_published"
                :href="home()"
                class="rounded-full border border-brand-green px-5 py-2.5 text-sm font-semibold text-brand-green"
            >
                Wstecz
            </Link>
            <span v-else />
            <button
                type="button"
                :disabled="confirming || skills.length === 0"
                class="rounded-full bg-brand-green px-6 py-2.5 text-sm font-semibold whitespace-nowrap text-white hover:bg-brand-green-soft disabled:opacity-50"
                @click="confirmAndContinue"
            >
                Zatwierdź i przejdź dalej
            </button>
        </div>
    </div>
</template>
