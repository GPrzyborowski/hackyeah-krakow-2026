<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandLogo from '@/components/brand/BrandLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import MobileUserMenu from '@/components/mobile/MobileUserMenu.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useMobileTabs } from '@/composables/useMobileTabs';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { hasTabs } = useMobileTabs();

const page = usePage();
const isCandidate = computed(() => page.props.auth.role === 'candidate');
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
        :class="{
            'max-md:border-b-0 max-md:px-5': hasTabs,
            'border-b-0 md:px-6': isCandidate,
        }"
    >
        <Link v-if="hasTabs" :href="dashboard()" class="md:hidden">
            <BrandLogo />
        </Link>
        <div
            class="flex items-center gap-2"
            :class="{ 'max-md:hidden': hasTabs }"
        >
            <SidebarTrigger
                class="-ml-1"
                :class="{
                    'h-10 w-10 rounded-full text-brand-green hover:bg-white':
                        isCandidate,
                }"
            />
            <template
                v-if="!isCandidate && breadcrumbs && breadcrumbs.length > 0"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <NotificationBell />
            <div v-if="hasTabs" class="flex md:hidden">
                <MobileUserMenu />
            </div>
        </div>
    </header>
</template>
