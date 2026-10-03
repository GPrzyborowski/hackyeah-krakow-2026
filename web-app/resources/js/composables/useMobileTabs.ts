import { usePage } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    Briefcase,
    Building2,
    FileText,
    Home,
    MessageCircle,
    User,
    Users,
} from '@lucide/vue';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

export type MobileTab = {
    title: string;
    href: string;
    icon: LucideIcon;
    /**
     * Path prefix that marks the tab as active (defaults to the href path).
     */
    activePrefix?: string;
    /**
     * Match only the exact path instead of the path and its children.
     */
    exact?: boolean;
};

const candidateTabs: MobileTab[] = [
    { title: 'Start', href: '/candidate', icon: Home, exact: true },
    { title: 'Oferty', href: '/candidate/offers', icon: Briefcase },
    { title: 'Blog', href: '/blog', icon: FileText },
    { title: 'Asystent', href: '/assistant', icon: MessageCircle },
    { title: 'Profil', href: '/candidate/profile', icon: User },
];

const employerTabs: MobileTab[] = [
    { title: 'Start', href: '/employer', icon: Home, exact: true },
    { title: 'Kandydatki', href: '/employer/candidates', icon: Users },
    { title: 'Blog', href: '/blog', icon: FileText },
    { title: 'Czaty', href: '/conversations', icon: MessageCircle },
    { title: 'Firma', href: '/employer/company', icon: Building2 },
];

export type UseMobileTabsReturn = {
    tabs: ComputedRef<MobileTab[]>;
    hasTabs: ComputedRef<boolean>;
    isActiveTab: (tab: MobileTab) => boolean;
};

/**
 * Fixed bottom tab bar items for the signed-in user's role (none for admins).
 */
export function useMobileTabs(): UseMobileTabsReturn {
    const page = usePage();
    const { currentUrl } = useCurrentUrl();

    const tabs = computed<MobileTab[]>(() => {
        switch (page.props.auth.role) {
            case 'candidate':
                return candidateTabs;
            case 'employer':
                return employerTabs;
            default:
                return [];
        }
    });

    const hasTabs = computed(() => tabs.value.length > 0);

    function isActiveTab(tab: MobileTab): boolean {
        const prefix = tab.activePrefix ?? tab.href.split('?')[0];
        const path = currentUrl.value;

        if (tab.exact) {
            return path === prefix;
        }

        return path === prefix || path.startsWith(`${prefix}/`);
    }

    return { tabs, hasTabs, isActiveTab };
}
