<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';
import ChatBubble from '@/components/chat/ChatBubble.vue';
import CitationPills from '@/components/chat/CitationPills.vue';
import type { AssistantChatMessage } from '@/components/chat/types';
import { index, store } from '@/routes/assistant';

const props = defineProps<{
    messages: AssistantChatMessage[];
    suggestions: string[];
    disclaimer: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Asystent', href: index() }],
    },
});

const form = useForm({ question: '' });
const pendingQuestion = ref<string | null>(null);
const thread = ref<HTMLElement | null>(null);

function scrollBehavior(smooth: boolean): ScrollBehavior {
    return smooth &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? 'smooth'
        : 'auto';
}

function scrollToBottom(): void {
    void nextTick(() => {
        thread.value?.scrollTo({
            top: thread.value.scrollHeight,
            behavior: scrollBehavior(true),
        });
    });
}

/**
 * Brings the latest question to the top of the thread, so a long answer is read from its first line.
 */
function scrollToLatestQuestion(smooth: boolean): void {
    void nextTick(() => {
        const questions =
            thread.value?.querySelectorAll<HTMLElement>('[data-question]');
        const latest = questions?.[questions.length - 1];

        thread.value?.scrollTo({
            top: latest ? latest.offsetTop - 16 : thread.value.scrollHeight,
            behavior: scrollBehavior(smooth),
        });
    });
}

onMounted(() => scrollToLatestQuestion(false));
watch(
    () => props.messages.length,
    () => scrollToLatestQuestion(true),
);

function ask(question?: string): void {
    if (question !== undefined) {
        form.question = question;
    }

    if (form.question.trim() === '' || form.processing) {
        return;
    }

    pendingQuestion.value = form.question;
    scrollToBottom();

    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onFinish: () => {
            pendingQuestion.value = null;
        },
    });
}
</script>

<template>
    <Head title="Asystent" />

    <div
        class="mx-auto flex h-[calc(100dvh-9rem-env(safe-area-inset-bottom))] w-full max-w-3xl flex-col gap-4 p-4 md:h-[calc(100dvh-5rem)] md:p-8"
    >
        <header>
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-4xl"
            >
                Asystent AI
            </h1>
            <p
                class="mt-1 text-sm text-brand-green/80"
                :class="{ 'max-md:hidden': messages.length || pendingQuestion }"
            >
                Odpowiada na pytania o ciążę, urlopy, zasiłki i powrót do
                pracy na podstawie przepisów i tekstów z bloga. Pod każdą
                odpowiedzią pokazuje źródło. To informacja ogólna, a nie
                porada prawna.
            </p>
        </header>

        <div
            ref="thread"
            class="relative flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto rounded-3xl border border-brand-green/10 bg-white/60 p-4 [mask-image:linear-gradient(to_bottom,transparent,black_12px,black_calc(100%-12px),transparent)]"
            role="log"
            aria-live="polite"
            aria-label="Rozmowa z asystentem"
            tabindex="0"
            data-test="assistant-thread"
        >
            <div
                v-if="messages.length === 0 && !pendingQuestion"
                class="m-auto flex max-w-md flex-col items-center gap-3 text-center text-brand-green"
            >
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-brand-yellow"
                >
                    <Sparkles class="size-6" aria-hidden="true" />
                </div>
                <p class="font-semibold">Masz pytanie o ciążę albo powrót do pracy?</p>
                <p class="text-sm text-brand-green/80">
                    Np. czy musisz mówić o ciąży na rozmowie, ile trwa urlop
                    rodzicielski albo jak wrócić na część etatu.
                </p>
                <div
                    v-if="suggestions.length"
                    class="mt-2 flex flex-wrap justify-center gap-2"
                >
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        type="button"
                        :disabled="form.processing"
                        class="rounded-full border border-brand-green/60 bg-white px-4 py-2 text-xs font-medium text-brand-green transition hover:bg-brand-mint-soft disabled:opacity-50"
                        @click="ask(suggestion)"
                    >
                        {{ suggestion }}
                    </button>
                </div>
            </div>

            <template v-for="message in messages" :key="message.id">
                <ChatBubble
                    v-if="message.role === 'user'"
                    :mine="true"
                    data-question
                >
                    {{ message.content }}
                </ChatBubble>
                <div
                    v-else
                    class="flex max-w-[92%] flex-col gap-3 rounded-3xl rounded-bl-lg bg-white p-4 text-sm leading-relaxed text-brand-green shadow-sm md:max-w-[80%]"
                >
                    <p class="whitespace-pre-line">
                        <span class="sr-only">Asystent:</span>
                        {{ message.content }}
                    </p>
                    <CitationPills :citations="message.citations" />
                    <p class="text-xs text-brand-green/80">{{ disclaimer }}</p>
                </div>
            </template>

            <template v-if="pendingQuestion">
                <ChatBubble :mine="true">{{ pendingQuestion }}</ChatBubble>
                <div
                    class="flex w-24 items-center justify-center gap-1 rounded-3xl rounded-bl-lg bg-white p-4 shadow-sm"
                    role="status"
                >
                    <span class="sr-only">Asystent pisze odpowiedź…</span>
                    <span
                        v-for="dot in 3"
                        :key="dot"
                        aria-hidden="true"
                        class="size-2 animate-pulse rounded-full bg-brand-mint motion-reduce:animate-none"
                        :style="{ animationDelay: `${dot * 150}ms` }"
                    />
                </div>
            </template>
        </div>

        <div class="flex flex-col gap-3">
            <p
                v-if="form.errors.question"
                class="px-2 text-sm text-red-700"
                role="alert"
            >
                {{ form.errors.question }}
            </p>

            <form
                class="flex items-center gap-2 rounded-full bg-white p-2 shadow-sm has-[input:focus-visible]:ring-2 has-[input:focus-visible]:ring-brand-green"
                @submit.prevent="ask()"
            >
                <input
                    v-model="form.question"
                    type="text"
                    name="question"
                    aria-label="Twoje pytanie"
                    :aria-invalid="form.errors.question ? true : undefined"
                    maxlength="1000"
                    placeholder="Napisz pytanie…"
                    class="h-11 flex-1 bg-transparent px-4 text-sm text-brand-green outline-none placeholder:text-brand-green/70"
                />
                <button
                    type="submit"
                    :disabled="form.processing || form.question.trim() === ''"
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-green text-white transition hover:bg-brand-green-soft disabled:opacity-50"
                    aria-label="Zapytaj"
                >
                    <ArrowRight class="size-5" aria-hidden="true" />
                </button>
            </form>
        </div>
    </div>
</template>
