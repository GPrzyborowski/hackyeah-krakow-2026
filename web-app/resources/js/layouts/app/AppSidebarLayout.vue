<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import SkipLink from '@/components/SkipLink.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import MobileTabBar from '@/components/mobile/MobileTabBar.vue';
import { Toaster } from '@/components/ui/sonner';
import { computed } from 'vue';
import { useMobileTabs } from '@/composables/useMobileTabs';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { hasTabs } = useMobileTabs();

const contentClass = computed(() =>
    hasTabs.value
        ? 'min-w-0 overflow-x-clip focus:outline-none max-md:pb-[calc(5rem+env(safe-area-inset-bottom))]'
        : 'min-w-0 overflow-x-clip focus:outline-none',
);
</script>

<template>
    <AppShell variant="sidebar">
        <SkipLink />
        <AppSidebar />
        <AppContent
            id="main"
            tabindex="-1"
            variant="sidebar"
            :class="contentClass"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <MobileTabBar />
        <Toaster
            :mobile-offset="
                hasTabs
                    ? { bottom: 'calc(5.5rem + env(safe-area-inset-bottom))' }
                    : undefined
            "
        />
    </AppShell>
</template>
