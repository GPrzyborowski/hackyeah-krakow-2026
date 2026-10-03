<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, Plus, Sparkles, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import type { ProfileSkill } from '@/components/candidate/types';
import InputError from '@/components/InputError.vue';
import { confirm } from '@/routes/candidate/profile/skills';
import { destroy, store } from '@/routes/candidate/skills';

const props = defineProps<{
    skills: ProfileSkill[];
    skillSuggestions: string[];
}>();

const page = usePage();
const isAddingTag = ref(false);
const newTag = ref('');
const tagInput = ref<HTMLInputElement | null>(null);
const confirming = ref(false);

const unconfirmedCount = computed(
    () => props.skills.filter((skill) => !skill.confirmed).length,
);

const unusedSuggestions = computed(() => {
    const taken = new Set(
        props.skills.map((skill) => skill.name.toLowerCase()),
    );

    return props.skillSuggestions.filter(
        (name) => !taken.has(name.toLowerCase()),
    );
});

const errors = computed(() => page.props.errors as Record<string, string>);

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

function confirmSkills() {
    router.visit(confirm(), {
        preserveScroll: true,
        onStart: () => (confirming.value = true),
        onFinish: () => (confirming.value = false),
    });
}
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
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
                    aria-hidden="true"
                />
                {{ skill.name }}
                <span v-if="!skill.confirmed" class="sr-only"
                    >(do zatwierdzenia)</span
                >
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-white/20"
                    :aria-label="`Usuń ${skill.name}`"
                    @click="removeSkill(skill)"
                >
                    <X class="size-3" aria-hidden="true" />
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
                    list="profile-skill-suggestions"
                    placeholder="np. Excel"
                    aria-label="Nowa umiejętność"
                    class="w-40 rounded-full border border-brand-line px-3 py-1 text-xs text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40"
                    @keydown.esc="isAddingTag = false"
                />
                <datalist id="profile-skill-suggestions">
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
                <Plus class="size-3" aria-hidden="true" /> Dodaj tag
            </button>
        </div>
        <p v-if="skills.length === 0" class="mt-2 text-xs text-brand-green/80">
            Brak umiejętności. Dodaj tagi, żeby firmy mogły Cię znaleźć.
        </p>
        <InputError class="mt-1" :message="errors.name" />
        <InputError class="mt-1" :message="errors.skills" />

        <div
            v-if="unconfirmedCount > 0"
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-yellow/40 p-3 text-sm text-brand-green"
        >
            <p>
                Do zatwierdzenia: {{ unconfirmedCount }}. Pracodawcy widzą
                tylko zatwierdzone umiejętności.
            </p>
            <button
                type="button"
                :disabled="confirming"
                class="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-4 py-1.5 text-xs font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                @click="confirmSkills"
            >
                <Check class="size-3.5" aria-hidden="true" /> Zatwierdź
                wszystkie
            </button>
        </div>
    </div>
</template>
