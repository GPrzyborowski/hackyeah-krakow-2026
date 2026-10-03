<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import KeywordChipsInput from '@/components/admin/KeywordChipsInput.vue';
import { index, store, update } from '@/routes/admin/legal-sources';

type EditableLegalSource = {
    id: number;
    act: string;
    article: string;
    title: string;
    content: string;
    keywords: string[];
};

const props = defineProps<{
    source: EditableLegalSource | null;
    acts: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Źródła prawne', href: index() }],
    },
});

const form = useForm({
    act: props.source?.act ?? '',
    article: props.source?.article ?? '',
    title: props.source?.title ?? '',
    content: props.source?.content ?? '',
    keywords: [...(props.source?.keywords ?? [])],
});

const pageTitle = computed(() =>
    props.source ? 'Edytuj źródło prawne' : 'Nowe źródło prawne',
);

const keywordErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([field]) => field.startsWith('keywords'))
        .map(([, message]) => message)
        .join(' '),
);

const superscripts = ['¹', '²', '³', '⁴', '⁵'];

function appendSuperscript(character: string): void {
    form.article = `${form.article}${character}`;
}

function submit(): void {
    if (props.source) {
        form.put(update.url(props.source.id), { preserveScroll: true });

        return;
    }

    form.post(store.url(), { preserveScroll: true });
}

const fieldClass =
    'mt-1.5 h-11 w-full rounded-2xl border border-brand-line bg-white px-4 text-sm font-normal text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40';
const labelClass = 'block text-xs font-semibold text-brand-green';
const hintClass = 'mt-1 block font-normal text-brand-green/80';
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mx-auto w-full max-w-3xl p-4 md:p-8">
        <Link
            :href="index()"
            class="inline-flex items-center gap-1 text-sm text-brand-green/80 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wszystkie źródła
        </Link>
        <h1
            class="mt-2 text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
        >
            {{ pageTitle }}
        </h1>

        <form
            class="mt-6 space-y-5 rounded-3xl bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                <label :class="labelClass">
                    Akt prawny
                    <input
                        v-model="form.act"
                        type="text"
                        list="legal-acts"
                        :class="fieldClass"
                        placeholder="np. Kodeks pracy"
                        required
                    />
                    <datalist id="legal-acts">
                        <option v-for="act in acts" :key="act" :value="act" />
                    </datalist>
                    <InputError :message="form.errors.act" />
                </label>
                <div>
                    <label :class="labelClass">
                        Artykuł
                        <input
                            v-model="form.article"
                            type="text"
                            :class="fieldClass"
                            placeholder="np. 22¹"
                            required
                        />
                    </label>
                    <span :class="[hintClass, 'text-xs']">
                        Bez „art.”. Indeksy górne wpisuj znakami Unicode:
                    </span>
                    <span class="mt-1 flex gap-1">
                        <button
                            v-for="character in superscripts"
                            :key="character"
                            type="button"
                            class="size-7 rounded-full bg-brand-cream text-sm font-semibold text-brand-green hover:bg-brand-mint-soft"
                            :aria-label="`Dopisz indeks górny ${character}`"
                            @click="appendSuperscript(character)"
                        >
                            {{ character }}
                        </button>
                    </span>
                    <InputError :message="form.errors.article" />
                </div>
            </div>

            <label :class="labelClass">
                Tytuł
                <input
                    v-model="form.title"
                    type="text"
                    :class="fieldClass"
                    placeholder="np. Zakaz pytania o ciążę"
                    required
                />
                <InputError :message="form.errors.title" />
            </label>

            <label :class="labelClass">
                Treść przepisu
                <textarea
                    v-model="form.content"
                    rows="8"
                    :class="[fieldClass, 'h-auto py-3']"
                    required
                />
                <span :class="hintClass"
                    >Asystent cytuje ten tekst w odpowiedziach. Pisz prostym
                    językiem.</span
                >
                <InputError :message="form.errors.content" />
            </label>

            <div>
                <label :class="labelClass" for="legal-source-keywords"
                    >Słowa kluczowe</label
                >
                <KeywordChipsInput
                    id="legal-source-keywords"
                    v-model="form.keywords"
                />
                <span :class="[hintClass, 'text-xs']"
                    >Oddzielaj przecinkiem lub Enterem. Frazy, którymi
                    użytkowniczki pytają asystenta, np. „pytanie o ciążę”.</span
                >
                <InputError :message="keywordErrors" />
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="h-11 rounded-full bg-brand-green px-6 text-sm font-semibold text-white transition hover:bg-brand-green-soft disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ source ? 'Zapisz zmiany' : 'Dodaj źródło' }}
                </button>
            </div>
        </form>
    </div>
</template>
