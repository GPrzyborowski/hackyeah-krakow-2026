<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BellOff } from '@lucide/vue';
import { computed } from 'vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import type { AppNotification } from '@/components/notifications/types';
import { Button } from '@/components/ui/button';
import { index, readAll } from '@/routes/notifications';

const props = defineProps<{
    items: {
        data: AppNotification[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Powiadomienia', href: index() }],
    },
});

const hasUnread = computed(() =>
    props.items.data.some((notification) => !notification.read),
);

function markAllRead(): void {
    router.post(readAll().url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Powiadomienia" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1
                    class="text-3xl font-extrabold tracking-tight text-brand-green md:text-4xl"
                >
                    Powiadomienia
                </h1>
                <p class="mt-1 text-sm text-brand-green/80">
                    Zaproszenia, odpowiedzi i nowe wiadomości w jednym miejscu.
                </p>
            </div>
            <Button
                v-if="hasUnread"
                variant="outline"
                class="rounded-full"
                data-test="mark-all-read"
                @click="markAllRead"
            >
                Oznacz wszystkie jako przeczytane
            </Button>
        </header>

        <section
            v-if="items.data.length"
            class="flex flex-col gap-1 rounded-3xl bg-white p-3 shadow-sm"
        >
            <NotificationItem
                v-for="notification in items.data"
                :key="notification.id"
                :notification="notification"
            />
        </section>

        <div
            v-else
            class="flex flex-col items-center gap-3 rounded-3xl bg-white p-10 text-center text-brand-green"
        >
            <span
                class="flex size-12 items-center justify-center rounded-full bg-brand-mint-soft"
            >
                <BellOff class="size-6" />
            </span>
            <p class="font-semibold">Na razie cisza.</p>
            <p class="text-sm text-brand-green/80">
                Damy znać, gdy pojawi się zaproszenie albo nowa wiadomość.
            </p>
        </div>

        <nav
            v-if="items.last_page > 1"
            class="flex items-center justify-between text-sm text-brand-green"
        >
            <Link
                v-if="items.prev_page_url"
                :href="items.prev_page_url"
                class="rounded-full bg-white px-4 py-2 font-medium hover:bg-brand-mint-soft"
            >
                Nowsze
            </Link>
            <span v-else />
            <span class="text-brand-green/80">
                Strona {{ items.current_page }} z
                {{ items.last_page }}
            </span>
            <Link
                v-if="items.next_page_url"
                :href="items.next_page_url"
                class="rounded-full bg-white px-4 py-2 font-medium hover:bg-brand-mint-soft"
            >
                Starsze
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
