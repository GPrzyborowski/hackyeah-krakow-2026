<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{
    id?: string;
}>();

const keywords = defineModel<string[]>({ required: true });

const draft = ref('');

/**
 * Add every comma-separated phrase from the input, skipping duplicates (case-insensitive).
 */
function commitDraft(): void {
    const taken = keywords.value.map((keyword) => keyword.toLowerCase());
    const additions: string[] = [];

    for (const part of draft.value.split(',')) {
        const keyword = part.trim();

        if (keyword !== '' && !taken.includes(keyword.toLowerCase())) {
            taken.push(keyword.toLowerCase());
            additions.push(keyword);
        }
    }

    keywords.value = [...keywords.value, ...additions];
    draft.value = '';
}

function onInput(): void {
    if (draft.value.includes(',')) {
        commitDraft();
    }
}

function onBackspace(): void {
    if (draft.value === '' && keywords.value.length > 0) {
        keywords.value = keywords.value.slice(0, -1);
    }
}

function remove(keyword: string): void {
    keywords.value = keywords.value.filter((item) => item !== keyword);
}
</script>

<template>
    <div
        class="mt-1.5 flex min-h-11 flex-wrap items-center gap-2 rounded-2xl border border-brand-green/20 bg-white px-3 py-2 focus-within:border-brand-green focus-within:ring-2 focus-within:ring-brand-mint/50"
    >
        <span
            v-for="keyword in keywords"
            :key="keyword"
            class="inline-flex items-center gap-1 rounded-full bg-brand-mint-soft px-3 py-1 text-xs font-semibold text-brand-green"
            data-test="keyword-chip"
        >
            {{ keyword }}
            <button
                type="button"
                class="rounded-full p-0.5 hover:bg-white/70"
                :aria-label="`Usuń słowo kluczowe ${keyword}`"
                @click="remove(keyword)"
            >
                <X class="size-3" />
            </button>
        </span>
        <input
            :id="id"
            v-model="draft"
            type="text"
            class="min-w-32 flex-1 bg-transparent text-sm font-normal text-brand-green outline-none"
            placeholder="np. urlop macierzyński, zasiłek"
            @input="onInput"
            @keydown.enter.prevent="commitDraft"
            @keydown.backspace="onBackspace"
            @blur="commitDraft"
        />
    </div>
</template>
