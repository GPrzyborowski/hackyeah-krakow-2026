import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'MomJobs';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome' || name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

let lastVisitedPath =
    typeof window !== 'undefined' ? window.location.pathname : '';

// Move focus to the main landmark after navigating to another page, so
// keyboard and screen reader users start reading the new content. Partial
// reloads (polling, filters on the same path) keep focus where it was.
router.on('navigate', () => {
    const currentPath = window.location.pathname;

    if (currentPath === lastVisitedPath) {
        return;
    }

    lastVisitedPath = currentPath;

    requestAnimationFrame(() => {
        const main = document.getElementById('main');
        const active = document.activeElement;

        // Respect pages that autofocus a field inside the new content.
        if (!main || (active && active !== main && main.contains(active))) {
            return;
        }

        main.focus({ preventScroll: true });
    });
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
