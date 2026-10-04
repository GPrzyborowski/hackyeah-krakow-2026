<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import BrandLogo from '@/components/brand/BrandLogo.vue';
import CandidateAvatar from '@/components/candidate/CandidateAvatar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { candidateNavSections } from '@/composables/useMainNav';
import { home } from '@/routes/candidate';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);
const firstName = computed(() => user.value.name.split(' ')[0]);
const { isMobile, state } = useSidebar();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

/**
 * Start matches only its own page, other sections stay active on their sub-pages.
 */
function isActive(item: NavItem): boolean {
    return item.href === '/candidate'
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="floating"
        class="*:data-[sidebar=sidebar]:rounded-[2rem]! *:data-[sidebar=sidebar]:border-0! *:data-[sidebar=sidebar]:bg-white! *:data-[sidebar=sidebar]:shadow-[0_8px_32px_rgba(20,63,59,0.08)]! dark:*:data-[sidebar=sidebar]:bg-neutral-900!"
        data-test="candidate-sidebar"
    >
        <SidebarHeader
            class="px-4 pt-6 pb-2 group-data-[collapsible=icon]:px-2"
        >
            <Link
                :href="home()"
                aria-label="mumjobs – start"
                class="flex items-center rounded-full focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
            >
                <BrandLogo class="h-9 group-data-[collapsible=icon]:hidden" />
                <AppLogoIcon
                    class="hidden size-8 group-data-[collapsible=icon]:block"
                    aria-hidden="true"
                />
            </Link>
        </SidebarHeader>

        <SidebarContent class="gap-1 px-2 group-data-[collapsible=icon]:px-0">
            <SidebarGroup
                v-for="section in candidateNavSections"
                :key="section.title"
                class="py-1"
            >
                <SidebarGroupLabel
                    class="px-4 text-[11px] font-semibold tracking-wider text-brand-green/60 uppercase dark:text-neutral-400"
                >
                    {{ section.title }}
                </SidebarGroupLabel>
                <SidebarMenu class="gap-0.5">
                    <SidebarMenuItem
                        v-for="item in section.items"
                        :key="item.title"
                    >
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item)"
                            :tooltip="item.title"
                            class="h-11 gap-3 rounded-full px-4 text-[15px] font-medium text-brand-green hover:bg-brand-mint-soft hover:text-brand-green data-[active=true]:bg-brand-green data-[active=true]:font-semibold data-[active=true]:text-white dark:text-neutral-200 dark:hover:bg-white/10 dark:data-[active=true]:bg-brand-mint dark:data-[active=true]:text-brand-green group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0! [&>svg]:size-5"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span
                                    class="group-data-[collapsible=icon]:hidden"
                                    >{{ item.title }}</span
                                >
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="p-3 group-data-[collapsible=icon]:p-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                size="lg"
                                class="h-14 gap-3 rounded-3xl bg-brand-cream px-3 text-brand-green group-data-[collapsible=icon]:rounded-full! group-data-[collapsible=icon]:bg-transparent hover:bg-brand-mint-soft hover:text-brand-green data-[state=open]:bg-brand-mint-soft dark:bg-white/5 dark:text-neutral-100"
                                data-test="sidebar-menu-button"
                            >
                                <CandidateAvatar
                                    :name="user.name"
                                    :photo-url="user.avatar"
                                    size="sm"
                                    class="group-data-[collapsible=icon]:size-8"
                                />
                                <span
                                    class="grid min-w-0 flex-1 text-left leading-tight"
                                >
                                    <span class="truncate font-semibold">
                                        {{ firstName }}
                                    </span>
                                    <span
                                        class="truncate text-xs text-brand-green/70 dark:text-neutral-400"
                                    >
                                        Konto i ustawienia
                                    </span>
                                </span>
                                <ChevronsUpDown
                                    class="ml-auto size-4 text-brand-green/60"
                                />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-2xl"
                            :side="
                                isMobile
                                    ? 'bottom'
                                    : state === 'collapsed'
                                      ? 'left'
                                      : 'top'
                            "
                            align="end"
                            :side-offset="8"
                        >
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
</template>
