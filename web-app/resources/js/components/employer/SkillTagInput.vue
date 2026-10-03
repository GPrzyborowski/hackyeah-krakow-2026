<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, useId } from 'vue';
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
const announcement = ref('');

const baseId = useId();
const labelId = `${baseId}-label`;
const listboxId = `${baseId}-listbox`;

function optionId(index: number): string {
    return `${baseId}-option-${index}`;
}

const activeDescendant = computed(() =>
    isOpen.value && visibleSuggestions.value[highlighted.value]
        ? optionId(highlighted.value)
        : undefined,
);

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
        announcement.value = `Dodano ${trimmed}`;
    }

    input.value = '';
    isOpen.value = false;
}

function removeTag(name: string): void {
    tags.value = tags.value.filter((tag) => tag !== name);
    announcement.value = `Usunięto ${name}`;
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
        <p :id="labelId" class="mb-2 font-semibold text-brand-green">
            {{ label }}
        </p>
        <p class="sr-only" aria-live="polite" role="status">
            {{ announcement }}
        </p>
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
                    class="rounded-full opacity-80 hover:opacity-100"
                    :aria-label="`Usuń ${tag}`"
                    @click="removeTag(tag)"
                >
                    <X class="size-3" aria-hidden="true" />
                </button>
            </span>

            <div class="relative">
                <button
                    v-if="!isEditing"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-brand-green/60 bg-white px-3 py-1 text-xs font-medium text-brand-green hover:bg-brand-mint-soft"
                    :aria-label="`Dodaj tag: ${label}`"
                    @click="startEditing"
                >
                    <Plus class="size-3" aria-hidden="true" /> Dodaj tag
                </button>
                <input
                    v-else
                    ref="inputElement"
                    v-model="input"
                    type="text"
                    class="w-48 rounded-full border border-brand-line bg-white px-3 py-1 text-xs text-brand-green outline-none focus:ring-2 focus:ring-brand-green"
                    placeholder="Wpisz umiejętność…"
                    role="combobox"
                    :aria-labelledby="labelId"
                    aria-autocomplete="list"
                    :aria-controls="listboxId"
                    :aria-activedescendant="activeDescendant"
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
                    :id="listboxId"
                    role="listbox"
                    :aria-labelledby="labelId"
                >
                    <li
                        v-for="(skill, index) in visibleSuggestions"
                        :id="optionId(index)"
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
                        role="option"
                        :aria-selected="false"
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
