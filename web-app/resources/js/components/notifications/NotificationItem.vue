<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Bell,
    CircleCheck,
    CircleX,
    MessageCircle,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import type { AppNotification } from '@/components/notifications/types';
import { read } from '@/routes/notifications';

const props = defineProps<{
    notification: AppNotification;
}>();

const icon = computed(() => {
    switch (props.notification.kind) {
        case 'new_message':
            return MessageCircle;
        case 'invitation_accepted':
        case 'pair_invitation_accepted':
            return CircleCheck;
        case 'pair_invitation_received':
            return UsersRound;
        case 'invitation_declined':
            return CircleX;
        default:
            return Bell;
    }
});

function open(): void {
    router.post(read(props.notification.id).url);
}
</script>

<template>
    <button
        type="button"
        class="flex w-full items-start gap-3 rounded-2xl p-3 text-left transition outline-none hover:bg-brand-mint-soft/60 focus-visible:ring-2 focus-visible:ring-brand-green data-[highlighted]:bg-brand-mint-soft/60 data-[highlighted]:ring-2 data-[highlighted]:ring-brand-green"
        :class="notification.read ? 'bg-white' : 'bg-brand-cream/70'"
        data-test="notification-item"
        @click="open"
    >
        <span
            class="flex size-8 shrink-0 items-center justify-center rounded-full"
            :class="
                notification.read
                    ? 'bg-brand-mint-soft text-brand-green'
                    : 'bg-brand-yellow text-brand-green'
            "
        >
            <component :is="icon" class="size-4" aria-hidden="true" />
        </span>
        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span
                class="text-sm leading-snug text-brand-green"
                :class="{ 'font-semibold': !notification.read }"
            >
                {{ notification.title }}
            </span>
            <span
                v-if="notification.body"
                class="truncate text-xs text-brand-green/80"
            >
                {{ notification.body }}
            </span>
            <span
                v-if="notification.created_at_diff"
                class="text-xs text-brand-green/80"
            >
                {{ notification.created_at_diff }}
            </span>
        </span>
        <span
            v-if="!notification.read"
            class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-peach"
            aria-hidden="true"
        />
        <span v-if="!notification.read" class="sr-only">(nieprzeczytane)</span>
    </button>
</template>
