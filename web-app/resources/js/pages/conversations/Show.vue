<script setup lang="ts">
import { Form, Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Mail, SendHorizontal, Star, UsersRound } from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';
import ChatBubble from '@/components/chat/ChatBubble.vue';
import { formatBubbleTime } from '@/components/chat/format';
import ModerationHint from '@/components/chat/ModerationHint.vue';
import type {
    ConversationCounterpart,
    ConversationMessage,
} from '@/components/chat/types';
import { index } from '@/routes/conversations';
import { store } from '@/routes/conversations/messages';
import type { UserRole } from '@/types';

const props = defineProps<{
    conversation: {
        id: number;
        offer_title: string;
        counterpart: ConversationCounterpart;
        pair_partner_name: string | null;
    };
    viewerRole: UserRole;
    messages: ConversationMessage[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Czaty', href: index() }],
    },
});

usePoll(4000, { only: ['messages'] });

const thread = ref<HTMLElement | null>(null);

function scrollToBottom(): void {
    void nextTick(() => {
        thread.value?.scrollTo({ top: thread.value.scrollHeight });
    });
}

onMounted(scrollToBottom);
watch(() => props.messages.length, scrollToBottom);

function submitOnEnter(event: KeyboardEvent, submit: () => void): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}
</script>

<template>
    <Head :title="`Czat – ${conversation.counterpart.name}`" />

    <div
        class="mx-auto flex h-[calc(100svh-9rem-env(safe-area-inset-bottom))] w-full max-w-3xl flex-col gap-4 p-4 md:h-[calc(100svh-5rem)] md:p-8"
    >
        <header
            class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow-sm"
        >
            <Link
                :href="index()"
                class="rounded-full p-2 text-brand-green transition hover:bg-brand-mint-soft"
                aria-label="Wróć do listy czatów"
            >
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-bold text-brand-green">
                    {{ conversation.counterpart.name }}
                </h1>
                <p class="truncate text-sm text-brand-green/80">
                    {{ conversation.offer_title }}
                </p>
                <span
                    v-if="conversation.pair_partner_name"
                    class="mt-1.5 inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-peach/60 px-3 py-1 text-xs font-semibold text-brand-green"
                    data-test="pair-chip"
                >
                    <UsersRound class="size-3.5 shrink-0" />
                    <span class="truncate"
                        >Para job-sharing z
                        {{ conversation.pair_partner_name }}</span
                    >
                </span>
            </div>
            <a
                v-if="conversation.counterpart.type === 'candidate'"
                :href="`mailto:${conversation.counterpart.email}`"
                class="hidden items-center gap-1.5 rounded-full bg-brand-mint-soft px-3 py-1.5 text-xs font-medium text-brand-green sm:inline-flex"
            >
                <Mail class="size-3.5" />
                {{ conversation.counterpart.email }}
            </a>
            <span
                v-else-if="conversation.counterpart.rating !== null"
                class="inline-flex items-center gap-1 rounded-full bg-brand-yellow px-3 py-1.5 text-xs font-semibold text-brand-green"
                title="Średnia ocena firmy"
            >
                <Star class="size-3.5 fill-current" />
                {{
                    conversation.counterpart.rating.toFixed(1).replace('.', ',')
                }}
            </span>
        </header>

        <div
            ref="thread"
            class="flex flex-1 flex-col gap-3 overflow-y-auto rounded-3xl bg-brand-cream/60 p-4"
            role="log"
            aria-live="polite"
            aria-relevant="additions"
            aria-label="Wiadomości"
            tabindex="0"
            data-test="message-thread"
        >
            <p
                v-if="messages.length === 0"
                class="m-auto max-w-sm text-center text-sm text-brand-green/80"
            >
                <template v-if="viewerRole === 'employer'">
                    Kandydatka przyjęła zaproszenie. Zaproponuj termin rozmowy i
                    zapytaj o dostępność.
                </template>
                <template v-else>
                    Przyjęłaś zaproszenie. Firma widzi teraz Twoje imię,
                    nazwisko i e-mail.
                </template>
            </p>
            <ChatBubble
                v-for="message in messages"
                :key="message.id"
                :mine="message.is_mine"
                :speaker="
                    message.is_mine ? 'Ty' : conversation.counterpart.name
                "
                :meta="formatBubbleTime(message.created_at)"
            >
                {{ message.body }}
            </ChatBubble>
        </div>

        <Form
            v-bind="store.form(conversation.id)"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="flex flex-col gap-3"
            #default="{ errors, processing, submit }"
        >
            <ModerationHint
                v-if="errors.body_suggestion"
                :reason="errors.body"
                :suggestion="errors.body_suggestion"
            />
            <p
                v-else-if="errors.body"
                class="px-2 text-sm text-red-700"
                role="alert"
            >
                {{ errors.body }}
            </p>
            <div
                class="flex items-end gap-2 rounded-3xl bg-white p-2 shadow-sm has-[textarea:focus-visible]:ring-2 has-[textarea:focus-visible]:ring-brand-green"
            >
                <textarea
                    name="body"
                    aria-label="Treść wiadomości"
                    rows="1"
                    required
                    maxlength="2000"
                    placeholder="Napisz wiadomość…"
                    class="max-h-40 min-h-11 flex-1 resize-none rounded-2xl bg-transparent px-3 py-2.5 text-sm text-brand-green outline-none placeholder:text-brand-green/70"
                    @keydown="submitOnEnter($event, submit)"
                />
                <button
                    type="submit"
                    :disabled="processing"
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-green text-white transition hover:bg-brand-green-soft disabled:opacity-50"
                    aria-label="Wyślij"
                >
                    <SendHorizontal class="size-5" aria-hidden="true" />
                </button>
            </div>
        </Form>
    </div>
</template>
