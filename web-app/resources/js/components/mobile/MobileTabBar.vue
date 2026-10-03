<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useMobileTabs } from '@/composables/useMobileTabs';

const { tabs, hasTabs, isActiveTab } = useMobileTabs();
</script>

<template>
    <nav
        v-if="hasTabs"
        aria-label="Nawigacja główna"
        class="fixed inset-x-0 bottom-0 z-40 rounded-t-3xl border-t border-black/5 bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_24px_rgba(20,63,59,0.08)] md:hidden dark:border-white/10 dark:bg-neutral-900"
        data-test="mobile-tab-bar"
    >
        <ul class="mx-auto flex max-w-lg items-stretch justify-between px-2">
            <li v-for="tab in tabs" :key="tab.title" class="flex-1">
                <Link
                    :href="tab.href"
                    prefetch
                    class="flex h-16 flex-col items-center justify-center gap-1 text-[11px] transition-colors"
                    :class="
                        isActiveTab(tab)
                            ? 'font-bold text-brand-green dark:text-brand-mint'
                            : 'font-medium text-neutral-500 hover:text-brand-green dark:text-neutral-400'
                    "
                    :aria-current="isActiveTab(tab) ? 'page' : undefined"
                >
                    <component
                        :is="tab.icon"
                        class="size-5"
                        :stroke-width="isActiveTab(tab) ? 2.4 : 1.8"
                    />
                    <span>{{ tab.title }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
