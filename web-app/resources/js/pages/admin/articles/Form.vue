<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MarkdownPreview from '@/components/admin/MarkdownPreview.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { index, preview, store, update } from '@/routes/admin/articles';

type ArticleCategoryOption = { value: string; label: string };

type EditableArticle = {
    id: number;
    title: string;
    slug: string;
    category: string;
    excerpt: string;
    body: string;
    reading_minutes: number;
    is_featured: boolean;
    published_at: string | null;
};

const props = defineProps<{
    article: EditableArticle | null;
    categories: ArticleCategoryOption[];
    wordsPerMinute: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Artykuły', href: index() }],
    },
});

/**
 * Same heuristic as the server: tokens with at least one letter or digit count as words.
 */
function estimateReadingMinutes(markdown: string): number {
    const words = markdown.match(/\S*[\p{L}\p{N}]\S*/gu)?.length ?? 0;

    return Math.max(1, Math.ceil(words / props.wordsPerMinute));
}

function slugify(text: string): string {
    return text
        .toLowerCase()
        .replace(/ł/g, 'l')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function toLocalInputValue(date: Date): string {
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const hasCustomReadingTime =
    props.article !== null &&
    props.article.reading_minutes !==
        estimateReadingMinutes(props.article.body);

const form = useForm({
    title: props.article?.title ?? '',
    slug: props.article?.slug ?? '',
    category: props.article?.category ?? props.categories[0]?.value ?? '',
    excerpt: props.article?.excerpt ?? '',
    body: props.article?.body ?? '',
    reading_minutes: (hasCustomReadingTime
        ? props.article?.reading_minutes
        : null) as number | null,
    is_featured: props.article?.is_featured ?? false,
    published_at: props.article?.published_at
        ? toLocalInputValue(new Date(props.article.published_at))
        : '',
});

const isSlugTouched = ref(props.article !== null);

watch(
    () => form.title,
    (title) => {
        if (!isSlugTouched.value) {
            form.slug = slugify(title);
        }
    },
);

const estimatedMinutes = computed(() => estimateReadingMinutes(form.body));

const publicationHint = computed(() => {
    if (!form.published_at) {
        return 'Bez daty artykuł jest szkicem i nie pojawi się na blogu.';
    }

    return new Date(form.published_at) > new Date()
        ? 'Artykuł pojawi się na blogu automatycznie w wybranym terminie.'
        : 'Artykuł jest widoczny na blogu.';
});

const pageTitle = computed(() =>
    props.article ? 'Edytuj artykuł' : 'Nowy artykuł',
);

const activeTab = ref<'write' | 'preview'>('write');
const previewHtml = ref('');
const previewRequest = useHttp<{ body: string }, { html: string }>({
    body: '',
});

async function showPreview(): Promise<void> {
    activeTab.value = 'preview';
    previewRequest.body = form.body;

    try {
        const response = await previewRequest.post(preview.url());
        previewHtml.value = response.html;
    } catch {
        previewHtml.value = '';
    }
}

function publishNow(): void {
    form.published_at = toLocalInputValue(new Date());
}

function submit(): void {
    const options = { preserveScroll: true };

    form.transform((data) => ({
        ...data,
        published_at: data.published_at
            ? new Date(data.published_at).toISOString()
            : null,
    }));

    if (props.article) {
        form.put(update.url(props.article.id), options);

        return;
    }

    form.post(store.url(), options);
}

const fieldClass =
    'mt-1.5 h-11 w-full rounded-2xl border border-brand-green/20 bg-white px-4 text-sm font-normal text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-mint/50';
const labelClass = 'block text-xs font-semibold text-brand-green';
const tabClass = (isActive: boolean): string =>
    `rounded-full px-4 py-1.5 text-sm font-semibold transition ${isActive ? 'bg-brand-green text-white' : 'bg-brand-cream text-brand-green hover:bg-brand-mint-soft'}`;
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mx-auto w-full max-w-6xl p-4 md:p-8">
        <Link
            :href="index()"
            class="inline-flex items-center gap-1 text-sm text-brand-green/70 hover:text-brand-green"
        >
            <ArrowLeft class="size-4" /> Wszystkie artykuły
        </Link>
        <h1
            class="mt-2 text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
        >
            {{ pageTitle }}
        </h1>

        <form
            class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]"
            @submit.prevent="submit"
        >
            <div class="space-y-5">
                <section class="space-y-4 rounded-3xl bg-white p-6 shadow-sm">
                    <label :class="labelClass">
                        Tytuł
                        <input
                            v-model="form.title"
                            type="text"
                            :class="fieldClass"
                            required
                        />
                        <InputError :message="form.errors.title" />
                    </label>
                    <label :class="labelClass">
                        Adres (slug)
                        <span
                            class="mt-1.5 flex items-center rounded-2xl border border-brand-green/20 bg-white pl-4 focus-within:border-brand-green focus-within:ring-2 focus-within:ring-brand-mint/50"
                        >
                            <span
                                class="text-sm font-normal text-brand-green/60"
                                >/blog/</span
                            >
                            <input
                                v-model="form.slug"
                                type="text"
                                class="h-11 w-full bg-transparent pr-4 text-sm font-normal text-brand-green outline-none"
                                @input="isSlugTouched = true"
                            />
                        </span>
                        <span class="mt-1 block font-normal text-brand-green/60"
                            >Tworzony automatycznie z tytułu. Możesz go
                            zmienić.</span
                        >
                        <InputError :message="form.errors.slug" />
                    </label>
                    <label :class="labelClass">
                        Zajawka
                        <textarea
                            v-model="form.excerpt"
                            rows="3"
                            :class="[fieldClass, 'h-auto py-3']"
                            required
                        />
                        <InputError :message="form.errors.excerpt" />
                    </label>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-sm">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h2 class="text-xl font-semibold text-brand-green">
                            Treść
                        </h2>
                        <div class="flex gap-2" role="tablist">
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="activeTab === 'write'"
                                :class="tabClass(activeTab === 'write')"
                                @click="activeTab = 'write'"
                            >
                                Edycja
                            </button>
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="activeTab === 'preview'"
                                :class="tabClass(activeTab === 'preview')"
                                data-test="preview-tab"
                                @click="showPreview"
                            >
                                Podgląd
                            </button>
                        </div>
                    </div>

                    <div v-show="activeTab === 'write'" class="mt-4">
                        <textarea
                            v-model="form.body"
                            rows="18"
                            :class="[fieldClass, 'h-auto py-3 font-mono']"
                            aria-label="Treść w Markdown"
                            required
                        />
                        <p class="mt-1 text-xs text-brand-green/60">
                            Markdown: ## nagłówek, **pogrubienie**, - lista,
                            &gt; cytat, [link](https://…).
                        </p>
                        <InputError :message="form.errors.body" />
                    </div>
                    <div
                        v-show="activeTab === 'preview'"
                        class="mt-4 min-h-40 rounded-2xl border border-brand-green/10 p-5"
                        :class="{ 'opacity-60': previewRequest.processing }"
                    >
                        <MarkdownPreview
                            v-if="previewHtml"
                            :html="previewHtml"
                        />
                        <p
                            v-else-if="!previewRequest.processing"
                            class="text-sm text-brand-green/60"
                        >
                            Brak treści do podglądu.
                        </p>
                    </div>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-4 lg:self-start">
                <section class="space-y-4 rounded-3xl bg-white p-6 shadow-sm">
                    <label :class="labelClass">
                        Kategoria
                        <select v-model="form.category" :class="fieldClass">
                            <option
                                v-for="category in categories"
                                :key="category.value"
                                :value="category.value"
                            >
                                {{ category.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category" />
                    </label>

                    <label :class="labelClass">
                        Czas czytania (min)
                        <input
                            v-model.number="form.reading_minutes"
                            type="number"
                            min="1"
                            max="120"
                            :class="fieldClass"
                            :placeholder="`Automatycznie: ${estimatedMinutes}`"
                        />
                        <span class="mt-1 block font-normal text-brand-green/60"
                            >Zostaw puste, aby liczyć z treści (ok.
                            {{ wordsPerMinute }} słów na minutę).</span
                        >
                        <InputError :message="form.errors.reading_minutes" />
                    </label>

                    <label :class="labelClass">
                        Data publikacji
                        <input
                            v-model="form.published_at"
                            type="datetime-local"
                            :class="fieldClass"
                        />
                        <span
                            class="mt-1 block font-normal text-brand-green/60"
                        >
                            {{ publicationHint }}
                        </span>
                        <InputError :message="form.errors.published_at" />
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full bg-brand-cream px-3 py-1 text-xs font-semibold text-brand-green hover:bg-brand-mint-soft"
                            @click="publishNow"
                        >
                            Teraz
                        </button>
                        <button
                            v-if="form.published_at"
                            type="button"
                            class="rounded-full bg-brand-cream px-3 py-1 text-xs font-semibold text-brand-green hover:bg-brand-mint-soft"
                            @click="form.published_at = ''"
                        >
                            Zapisz jako szkic
                        </button>
                    </div>

                    <label
                        class="flex items-start gap-3 rounded-2xl bg-brand-mint-soft/60 p-4 text-sm text-brand-green"
                    >
                        <Checkbox v-model="form.is_featured" class="mt-0.5" />
                        <span>
                            <span class="font-semibold">Wyróżniony</span>
                            <span class="block text-xs text-brand-green/70"
                                >Pokazywany jako główny artykuł bloga. Zastąpi
                                obecne wyróżnienie.</span
                            >
                        </span>
                    </label>
                    <InputError :message="form.errors.is_featured" />

                    <button
                        type="submit"
                        class="h-11 w-full rounded-full bg-brand-green px-6 text-sm font-semibold text-white transition hover:bg-brand-green-soft disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ article ? 'Zapisz zmiany' : 'Zapisz artykuł' }}
                    </button>
                </section>
            </aside>
        </form>
    </div>
</template>
