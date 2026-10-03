import { usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    BookOpen,
    Briefcase,
    Building2,
    FileText,
    Home,
    LayoutDashboard,
    Mail,
    MessageCircle,
    Scale,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Star,
    User,
    Users,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import type { NavItem } from '@/types';

export type NavSection = {
    title: string;
    items: NavItem[];
};

/**
 * Candidate navigation grouped into sections for the sidebar.
 */
export const candidateNavSections: NavSection[] = [
    {
        title: 'Szukam pracy',
        items: [
            { title: 'Start', href: '/candidate', icon: Home },
            { title: 'Oferty', href: '/candidate/offers', icon: Briefcase },
            {
                title: 'Zaproszenia',
                href: '/candidate/invitations',
                icon: Mail,
            },
            { title: 'Job sharing', href: '/job-sharing', icon: UsersRound },
        ],
    },
    {
        title: 'Rozmowy',
        items: [
            { title: 'Czaty', href: '/conversations', icon: MessageCircle },
            { title: 'Opinie o firmach', href: '/reviews', icon: Star },
        ],
    },
    {
        title: 'Dla mnie',
        items: [
            { title: 'Asystent', href: '/assistant', icon: Sparkles },
            { title: 'Blog', href: '/blog', icon: BookOpen },
            { title: 'Mój profil', href: '/candidate/profile', icon: User },
        ],
    },
];

const candidateItems: NavItem[] = candidateNavSections.flatMap(
    (section) => section.items,
);

const employerItems: NavItem[] = [
    { title: 'Start', href: '/employer', icon: Home },
    { title: 'Ogłoszenia', href: '/employer/offers', icon: Briefcase },
    { title: 'Kandydatki', href: '/employer/candidates', icon: Users },
    { title: 'Zaproszenia', href: '/employer/invitations', icon: Mail },
    { title: 'Czaty', href: '/conversations', icon: MessageCircle },
    { title: 'Konto firmy', href: '/employer/company', icon: Building2 },
    { title: 'Blog', href: '/blog', icon: BookOpen },
];

const adminItems: NavItem[] = [
    { title: 'Panel', href: '/admin', icon: LayoutDashboard },
    { title: 'Opinie do moderacji', href: '/admin/reviews', icon: ShieldCheck },
    { title: 'Firmy', href: '/admin/companies', icon: BadgeCheck },
    {
        title: 'Zablokowane wiadomości',
        href: '/admin/moderation',
        icon: ShieldAlert,
    },
    { title: 'Artykuły', href: '/admin/articles', icon: FileText },
    { title: 'Źródła prawne', href: '/admin/legal-sources', icon: Scale },
    { title: 'Blog', href: '/blog', icon: BookOpen },
];

/**
 * Main navigation items for the signed-in user's role.
 */
export function useMainNav() {
    const page = usePage();

    return computed<NavItem[]>(() => {
        if (page.props.auth.role === 'admin') {
            return adminItems;
        }

        return page.props.auth.role === 'employer'
            ? employerItems
            : candidateItems;
    });
}
