<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/brand/BrandLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center bg-background px-4 py-8 text-foreground sm:justify-center sm:py-12"
    >
        <div class="w-full max-w-md">
            <div class="flex justify-center">
                <Link
                    :href="home()"
                    class="inline-flex items-center"
                    aria-label="MomJobs – strona główna"
                >
                    <BrandLogo class="h-12" />
                </Link>
            </div>

            <div
                class="mt-8 rounded-3xl bg-card p-6 text-card-foreground shadow-sm sm:p-8"
            >
                <div class="mb-6 space-y-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>

            <p class="mt-6 text-center text-xs text-muted-foreground">
                MomJobs · praca dla przyszłych i obecnych mam
            </p>
        </div>
    </div>
</template>
