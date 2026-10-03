import { usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Briefcase,
    Building2,
    Home,
    Mail,
    MessageCircle,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import type { NavItem } from '@/types';

const candidateItems: NavItem[] = [
    { title: 'Start', href: '/candidate', icon: Home },
    { title: 'Oferty', href: '/candidate/offers', icon: Briefcase },
    { title: 'Zaproszenia', href: '/candidate/invitations', icon: Mail },
    { title: 'Czaty', href: '/conversations', icon: MessageCircle },
    { title: 'Asystent', href: '/assistant', icon: Sparkles },
    { title: 'Blog', href: '/blog', icon: BookOpen },
];

const employerItems: NavItem[] = [
    { title: 'Ogłoszenia', href: '/employer/offers', icon: Briefcase },
    { title: 'Kandydatki', href: '/employer/candidates', icon: Users },
    { title: 'Czaty', href: '/conversations', icon: MessageCircle },
    { title: 'Firma', href: '/employer/company', icon: Building2 },
    { title: 'Blog', href: '/blog', icon: BookOpen },
];

/**
 * Main navigation items for the signed-in user's role.
 */
export function useMainNav() {
    const page = usePage();

    return computed<NavItem[]>(() =>
        page.props.auth.role === 'employer' ? employerItems : candidateItems,
    );
}
