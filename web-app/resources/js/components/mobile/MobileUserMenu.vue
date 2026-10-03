<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import { useMainNav } from '@/composables/useMainNav';
import { useMobileTabs } from '@/composables/useMobileTabs';
import { toUrl } from '@/lib/utils';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
const mainNavItems = useMainNav();
const { tabs } = useMobileTabs();

/**
 * Sections reachable from the desktop sidebar but missing from the bottom tab bar.
 */
const extraNavItems = computed(() => {
    const tabPaths = tabs.value.map((tab) => tab.href.split('?')[0]);

    return mainNavItems.value.filter(
        (item) => !tabPaths.includes(toUrl(item.href)),
    );
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="rounded-full focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
            aria-label="Menu konta"
            data-test="mobile-user-menu"
        >
            <Avatar class="size-9 overflow-hidden rounded-full">
                <AvatarImage
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                />
                <AvatarFallback
                    class="rounded-full bg-brand-peach font-semibold text-brand-green"
                >
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="min-w-56 rounded-xl"
            side="bottom"
            align="end"
            :side-offset="8"
        >
            <template v-if="extraNavItems.length">
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        v-for="item in extraNavItems"
                        :key="item.title"
                        :as-child="true"
                    >
                        <Link
                            class="flex w-full cursor-pointer items-center"
                            :href="item.href"
                        >
                            <component
                                :is="item.icon"
                                v-if="item.icon"
                                class="mr-2 h-4 w-4"
                            />
                            {{ item.title }}
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
            </template>
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
