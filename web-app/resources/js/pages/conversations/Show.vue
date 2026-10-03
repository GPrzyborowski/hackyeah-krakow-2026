<script setup lang="ts">
import { Form, Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Mail, SendHorizontal, Star } from '@lucide/vue';
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
                <ArrowLeft class="size-5" />
            </Link>
            <div class="min-w-0 flex-1">
                <p class="truncate text-lg font-bold text-brand-green">
                    {{ conversation.counterpart.name }}
                </p>
                <p class="truncate text-sm text-brand-green/70">
                    {{ conversation.offer_title }}
                </p>
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
            data-test="message-thread"
        >
            <p
                v-if="messages.length === 0"
                class="m-auto max-w-sm text-center text-sm text-brand-green/60"
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
            <p v-else-if="errors.body" class="px-2 text-sm text-destructive">
                {{ errors.body }}
            </p>
            <div
                class="flex items-end gap-2 rounded-3xl bg-white p-2 shadow-sm"
            >
                <textarea
                    name="body"
                    rows="1"
                    required
                    maxlength="2000"
                    placeholder="Napisz wiadomość…"
                    class="max-h-40 min-h-11 flex-1 resize-none rounded-2xl bg-transparent px-3 py-2.5 text-sm text-brand-green outline-none placeholder:text-brand-green/40"
                    @keydown="submitOnEnter($event, submit)"
                />
                <button
                    type="submit"
                    :disabled="processing"
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-green text-white transition hover:bg-brand-green-soft disabled:opacity-50"
                    aria-label="Wyślij"
                >
                    <SendHorizontal class="size-5" />
                </button>
            </div>
        </Form>
    </div>
</template>
