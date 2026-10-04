<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import { formatMessageTime } from '@/components/chat/format';
import type { ConversationSummary } from '@/components/chat/types';
import { index, show } from '@/routes/conversations';

defineProps<{
    conversations: ConversationSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Czaty', href: index() }],
    },
});

const page = usePage();

const emptyStateText = computed(() =>
    page.props.auth.role === 'employer'
        ? 'Czat otwiera się, gdy kandydatka przyjmie Twoje zaproszenie.'
        : 'Czat otwiera się, gdy przyjmiesz zaproszenie od firmy.',
);
</script>

<template>
    <Head title="Czaty" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 md:p-8">
        <h1
            class="text-3xl font-extrabold tracking-tight text-brand-green md:text-4xl"
        >
            Czaty
        </h1>

        <div
            v-if="conversations.length === 0"
            class="flex flex-col items-center gap-3 rounded-3xl bg-white p-10 text-center text-brand-green shadow-sm"
        >
            <MessageCircle class="size-8 text-brand-mint" />
            <p class="font-semibold">Nie masz jeszcze rozmów.</p>
            <p class="text-sm text-brand-green/80">{{ emptyStateText }}</p>
        </div>

        <ul v-else class="flex flex-col gap-3">
            <li v-for="conversation in conversations" :key="conversation.id">
                <Link
                    :href="show(conversation.id)"
                    class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow-sm transition hover:shadow-md"
                    :data-test="`conversation-${conversation.id}`"
                >
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-mint-soft text-lg font-bold text-brand-green"
                    >
                        <UsersRound
                            v-if="conversation.is_team_chat"
                            class="size-5"
                            aria-hidden="true"
                        />
                        <template v-else>{{
                            conversation.counterpart_name.charAt(0)
                        }}</template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <p
                                class="truncate font-semibold text-brand-green"
                                :class="{
                                    'font-extrabold': conversation.has_unread,
                                }"
                            >
                                {{ conversation.counterpart_name }}
                            </p>
                            <span class="shrink-0 text-xs text-brand-green/80">
                                {{
                                    formatMessageTime(
                                        conversation.last_message_at,
                                    )
                                }}
                            </span>
                        </div>
                        <p
                            class="flex min-w-0 items-center gap-2 text-xs font-medium text-brand-green/80"
                        >
                            <span
                                v-if="conversation.is_team_chat"
                                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-peach/60 px-2 py-0.5 font-semibold text-brand-green"
                                data-test="team-chat-chip"
                            >
                                <UsersRound
                                    class="size-3 shrink-0"
                                    aria-hidden="true"
                                />
                                Para job sharing
                            </span>
                            <span class="truncate">{{
                                conversation.offer_title
                            }}</span>
                        </p>
                        <p
                            class="mt-1 truncate text-sm"
                            :class="
                                conversation.has_unread
                                    ? 'font-semibold text-brand-green'
                                    : 'text-brand-green/80'
                            "
                        >
                            {{
                                conversation.last_message ??
                                'Napisz pierwszą wiadomość'
                            }}
                        </p>
                    </div>
                    <span
                        v-if="conversation.has_unread"
                        class="size-2.5 shrink-0 rounded-full bg-brand-peach"
                        aria-label="Nowe wiadomości"
                    />
                </Link>
            </li>
        </ul>
    </div>
</template>
