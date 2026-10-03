<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import CompanyController from '@/actions/App/Http/Controllers/Employer/CompanyController';
import CompanyTeamController from '@/actions/App/Http/Controllers/Employer/CompanyTeamController';

defineProps<{
    active: 'profile' | 'team';
}>();

const tabs = [
    { key: 'profile', label: 'Profil firmy', href: CompanyController.edit() },
    { key: 'team', label: 'Zespół', href: CompanyTeamController.index() },
] as const;
</script>

<template>
    <nav aria-label="Sekcje firmy" class="flex flex-wrap gap-2">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            :aria-current="active === tab.key ? 'page' : undefined"
            class="rounded-full px-4 py-2 text-sm font-semibold transition"
            :class="
                active === tab.key
                    ? 'bg-brand-green text-white'
                    : 'bg-white text-brand-green hover:bg-brand-mint-soft'
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
