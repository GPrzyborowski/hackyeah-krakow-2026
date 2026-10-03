<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Wygląd',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Wygląd" />

    <h1 class="sr-only">Wygląd</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Wygląd"
            description="Wybierz motyw aplikacji"
        />
        <AppearanceTabs />
    </div>
</template>
