<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import type { NotificationsSummary } from '@/components/notifications/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index, readAll } from '@/routes/notifications';

const page = usePage();

const summary = computed(
    () => page.props.notifications as NotificationsSummary | null | undefined,
);
const unreadCount = computed(() => summary.value?.unread_count ?? 0);
const badgeLabel = computed(() =>
    unreadCount.value > 9 ? '9+' : String(unreadCount.value),
);

function markAllRead(): void {
    router.post(readAll().url, {}, { preserveScroll: true });
}
</script>

<template>
    <DropdownMenu v-if="summary">
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="relative rounded-full text-brand-green"
                :aria-label="`Powiadomienia (${unreadCount} nieprzeczytanych)`"
                data-test="notification-bell"
            >
                <Bell class="size-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-0.5 -right-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand-peach px-1 text-[10px] leading-5 font-bold text-brand-green"
                >
                    {{ badgeLabel }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="w-[min(22rem,calc(100vw-2rem))] rounded-3xl p-2"
        >
            <DropdownMenuLabel
                class="flex items-center justify-between gap-2 px-2 py-1.5"
            >
                <span class="font-semibold text-brand-green"
                    >Powiadomienia</span
                >
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-xs font-medium text-brand-green/70 underline-offset-2 hover:underline"
                    @click="markAllRead"
                >
                    Oznacz wszystkie jako przeczytane
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <div
                v-if="summary.latest.length"
                class="flex max-h-96 flex-col gap-1 overflow-y-auto"
            >
                <NotificationItem
                    v-for="notification in summary.latest"
                    :key="notification.id"
                    :notification="notification"
                />
            </div>
            <p v-else class="px-3 py-6 text-center text-sm text-brand-green/60">
                Nie masz jeszcze powiadomień.
            </p>
            <DropdownMenuSeparator />
            <Link
                :href="index()"
                class="block rounded-full px-3 py-2 text-center text-sm font-semibold text-brand-green hover:bg-brand-mint-soft"
            >
                Zobacz wszystkie
            </Link>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
