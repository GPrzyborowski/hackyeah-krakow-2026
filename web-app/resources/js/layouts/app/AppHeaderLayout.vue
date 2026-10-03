<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import SkipLink from '@/components/SkipLink.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="header">
        <SkipLink />
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent
            id="main"
            tabindex="-1"
            variant="header"
            class="focus:outline-none"
        >
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
