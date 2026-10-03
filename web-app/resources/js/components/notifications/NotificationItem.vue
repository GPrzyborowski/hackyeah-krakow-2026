<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bell, CircleCheck, CircleX, MessageCircle } from '@lucide/vue';
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
            return CircleCheck;
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
        class="flex w-full items-start gap-3 rounded-2xl p-3 text-left transition hover:bg-brand-mint-soft/60"
        :class="notification.read ? 'opacity-70' : 'bg-brand-cream/70'"
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
            <component :is="icon" class="size-4" />
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
                class="truncate text-xs text-brand-green/70"
            >
                {{ notification.body }}
            </span>
            <span
                v-if="notification.created_at_diff"
                class="text-xs text-brand-green/50"
            >
                {{ notification.created_at_diff }}
            </span>
        </span>
        <span
            v-if="!notification.read"
            class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-peach"
            aria-label="Nieprzeczytane"
        />
    </button>
</template>
