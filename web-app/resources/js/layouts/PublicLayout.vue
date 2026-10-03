<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import BrandLogo from '@/components/brand/BrandLogo.vue';
import MobileTabBar from '@/components/mobile/MobileTabBar.vue';
import SkipLink from '@/components/SkipLink.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useMobileTabs } from '@/composables/useMobileTabs';
import { dashboard, home, login, register } from '@/routes';
import {
    contact as legalContact,
    privacy as legalPrivacy,
    terms as legalTerms,
} from '@/routes/public/legal';
import { index as offersIndex } from '@/routes/public/offers';

type PublicNavItem = {
    title: string;
    href: string;
};

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

const isMenuOpen = ref(false);

const isSignedIn = computed(() => Boolean(page.props.auth.user));
const { hasTabs } = useMobileTabs();

const navItems = computed<PublicNavItem[]>(() => [
    { title: 'Jak to działa', href: `${home.url()}#jak-to-dziala` },
    { title: 'Job sharing', href: `${home.url()}#job-sharing` },
    { title: 'Asystent AI', href: '/assistant' },
    {
        title: 'Dla pracodawców',
        href: register.url({ query: { role: 'employer' } }),
    },
]);

const footerLinks: PublicNavItem[] = [
    { title: 'Blog', href: '/blog' },
    { title: 'Oferty pracy', href: offersIndex.url() },
    { title: 'Regulamin', href: legalTerms.url() },
    { title: 'Prywatność', href: legalPrivacy.url() },
    { title: 'Kontakt', href: legalContact.url() },
];

const isActive = (href: string): boolean =>
    !href.includes('?') && !href.includes('#') && isCurrentOrParentUrl(href);

const stopListening = router.on('navigate', () => {
    isMenuOpen.value = false;
});

onUnmounted(stopListening);
</script>

<template>
    <div class="flex min-h-svh flex-col bg-brand-cream text-brand-green">
        <SkipLink />
        <header class="relative z-30">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <Link :href="home()" aria-label="mumjobs – strona główna">
                    <BrandLogo />
                </Link>

                <nav
                    class="hidden items-center gap-7 text-sm font-medium md:flex"
                    aria-label="Główna nawigacja"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.title"
                        :href="item.href"
                        class="border-b-2 py-1 transition hover:border-brand-green/40"
                        :class="
                            isActive(item.href)
                                ? 'border-brand-green'
                                : 'border-transparent'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-2 md:flex">
                    <Link
                        v-if="isSignedIn"
                        :href="dashboard()"
                        class="rounded-full bg-brand-green px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                        data-test="public-dashboard-link"
                    >
                        Mój panel
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full border border-brand-green px-5 py-2.5 text-sm font-medium transition hover:bg-white"
                        >
                            Zaloguj się
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-full bg-brand-green px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-green-soft"
                        >
                            Załóż profil
                        </Link>
                    </template>
                </div>

                <button
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-full border border-brand-green/60 bg-white md:hidden"
                    :aria-expanded="isMenuOpen"
                    aria-controls="public-mobile-menu"
                    :aria-label="isMenuOpen ? 'Zamknij menu' : 'Otwórz menu'"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <X v-if="isMenuOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>

            <div
                v-show="isMenuOpen"
                id="public-mobile-menu"
                class="absolute inset-x-0 top-full border-t border-brand-green/10 bg-brand-cream px-4 pb-6 shadow-lg md:hidden"
            >
                <nav class="flex flex-col py-2" aria-label="Menu mobilne">
                    <Link
                        v-for="item in navItems"
                        :key="item.title"
                        :href="item.href"
                        class="border-b border-brand-green/10 py-3 text-base font-medium"
                    >
                        {{ item.title }}
                    </Link>
                </nav>
                <div class="mt-4 flex flex-col gap-2">
                    <Link
                        v-if="isSignedIn"
                        :href="dashboard()"
                        class="rounded-full bg-brand-green px-5 py-3 text-center text-sm font-medium text-white"
                    >
                        Mój panel
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full border border-brand-green px-5 py-3 text-center text-sm font-medium"
                        >
                            Zaloguj się
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-full bg-brand-green px-5 py-3 text-center text-sm font-medium text-white"
                        >
                            Załóż profil
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main id="main" tabindex="-1" class="flex-1 focus:outline-none">
            <slot />
        </main>

        <footer
            class="border-t border-brand-green/10"
            :class="{
                'max-md:pb-[calc(5rem+env(safe-area-inset-bottom))]':
                    isSignedIn && hasTabs,
            }"
        >
            <div
                class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
            >
                <p class="text-brand-green/80">
                    mumjobs · praca dla przyszłych i obecnych mam
                </p>
                <nav class="flex gap-5 font-medium" aria-label="Stopka">
                    <Link
                        v-for="link in footerLinks"
                        :key="link.title"
                        :href="link.href"
                        class="hover:underline"
                        >{{ link.title }}</Link
                    >
                </nav>
            </div>
        </footer>

        <MobileTabBar v-if="isSignedIn" />
    </div>
</template>
