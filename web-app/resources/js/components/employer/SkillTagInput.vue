<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import SkillSearchController from '@/actions/App/Http/Controllers/Employer/SkillSearchController';

type SkillSuggestion = { id: number; name: string };

const props = withDefaults(
    defineProps<{
        label: string;
        variant?: 'required' | 'nice';
        excluded?: string[];
    }>(),
    { variant: 'required', excluded: () => [] },
);

const tags = defineModel<string[]>({ required: true });

const input = ref('');
const isEditing = ref(false);
const isOpen = ref(false);
const highlighted = ref(0);
const suggestions = ref<SkillSuggestion[]>([]);
const inputElement = ref<HTMLInputElement | null>(null);

const search = useHttp<{ q: string }, { data: SkillSuggestion[] }>({ q: '' });

const taken = computed(() =>
    [...tags.value, ...props.excluded].map((tag) => tag.toLowerCase()),
);

const visibleSuggestions = computed(() =>
    suggestions.value.filter(
        (skill) => !taken.value.includes(skill.name.toLowerCase()),
    ),
);

const fetchSuggestions = useDebounceFn(async () => {
    search.q = input.value.trim();

    try {
        const response = await search.get(SkillSearchController.url());
        suggestions.value = response.data;
        highlighted.value = 0;
    } catch {
        suggestions.value = [];
    }
}, 200);

function onInput(): void {
    isOpen.value = true;
    void fetchSuggestions();
}

function addTag(name: string): void {
    const trimmed = name.trim();

    if (trimmed !== '' && !taken.value.includes(trimmed.toLowerCase())) {
        tags.value = [...tags.value, trimmed];
    }

    input.value = '';
    isOpen.value = false;
}

function removeTag(name: string): void {
    tags.value = tags.value.filter((tag) => tag !== name);
}

function onEnter(): void {
    const suggestion = visibleSuggestions.value[highlighted.value];

    addTag(isOpen.value && suggestion ? suggestion.name : input.value);
}

function moveHighlight(step: number): void {
    const count = visibleSuggestions.value.length;

    if (count > 0) {
        highlighted.value = (highlighted.value + step + count) % count;
    }
}

function startEditing(): void {
    isEditing.value = true;
    isOpen.value = true;
    void fetchSuggestions();
    requestAnimationFrame(() => inputElement.value?.focus());
}

function onBlur(): void {
    window.setTimeout(() => {
        if (input.value.trim() !== '') {
            addTag(input.value);
        }

        isOpen.value = false;
        isEditing.value = false;
    }, 150);
}

function onBackspace(): void {
    if (input.value === '' && tags.value.length > 0) {
        removeTag(tags.value[tags.value.length - 1]);
    }
}
</script>

<template>
    <div>
        <p class="mb-2 font-semibold text-brand-green">{{ label }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <span
                v-for="tag in tags"
                :key="tag"
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                :class="
                    variant === 'required'
                        ? 'bg-brand-green text-white'
                        : 'border border-brand-green/30 bg-white text-brand-green'
                "
            >
                {{ tag }}
                <button
                    type="button"
                    class="rounded-full opacity-70 hover:opacity-100"
                    :aria-label="`Usuń ${tag}`"
                    @click="removeTag(tag)"
                >
                    <X class="size-3" />
                </button>
            </span>

            <div class="relative">
                <button
                    v-if="!isEditing"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-brand-green/30 bg-white px-3 py-1 text-xs font-medium text-brand-green hover:bg-brand-mint-soft"
                    @click="startEditing"
                >
                    <Plus class="size-3" /> Dodaj tag
                </button>
                <input
                    v-else
                    ref="inputElement"
                    v-model="input"
                    type="text"
                    class="w-48 rounded-full border border-brand-green/40 bg-white px-3 py-1 text-xs text-brand-green outline-none focus:ring-2 focus:ring-brand-mint"
                    placeholder="Wpisz umiejętność…"
                    role="combobox"
                    :aria-expanded="isOpen"
                    @input="onInput"
                    @keydown.enter.prevent="onEnter"
                    @keydown.down.prevent="moveHighlight(1)"
                    @keydown.up.prevent="moveHighlight(-1)"
                    @keydown.esc="isOpen = false"
                    @keydown.backspace="onBackspace"
                    @blur="onBlur"
                />
                <ul
                    v-if="
                        isEditing &&
                        isOpen &&
                        (visibleSuggestions.length > 0 || input.trim() !== '')
                    "
                    class="absolute top-full left-0 z-20 mt-1 max-h-60 w-60 overflow-auto rounded-2xl border border-brand-green/10 bg-white p-1 text-sm shadow-lg"
                    role="listbox"
                >
                    <li
                        v-for="(skill, index) in visibleSuggestions"
                        :key="skill.id"
                        role="option"
                        :aria-selected="index === highlighted"
                        class="cursor-pointer rounded-xl px-3 py-1.5 text-brand-green"
                        :class="
                            index === highlighted ? 'bg-brand-mint-soft' : ''
                        "
                        @mousedown.prevent="addTag(skill.name)"
                    >
                        {{ skill.name }}
                    </li>
                    <li
                        v-if="
                            input.trim() !== '' &&
                            !visibleSuggestions.some(
                                (skill) =>
                                    skill.name.toLowerCase() ===
                                    input.trim().toLowerCase(),
                            )
                        "
                        class="cursor-pointer rounded-xl px-3 py-1.5 text-brand-green/80 hover:bg-brand-mint-soft"
                        @mousedown.prevent="addTag(input)"
                    >
                        Dodaj „{{ input.trim() }}”
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
