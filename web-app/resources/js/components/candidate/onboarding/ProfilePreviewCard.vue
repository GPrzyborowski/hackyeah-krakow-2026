<script setup lang="ts">
import { formatShortDate } from '@/components/candidate/format';

const props = defineProps<{
    anonymousName: string;
    headline: string | null;
    summary: string | null;
    skills: string[];
    availableFrom: string | null;
}>();

</script>

<template>
    <section class="rounded-3xl bg-brand-green p-6 text-white">
        <h2 class="text-lg font-bold">Tak widzi Cię pracodawca</h2>
        <div class="mt-4 rounded-2xl bg-white p-5 text-brand-green">
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-peach text-lg font-bold"
                >
                    {{ anonymousName.charAt(0) }}
                </span>
                <div class="min-w-0">
                    <p class="text-lg font-bold">{{ anonymousName }}</p>
                    <p class="truncate text-xs text-brand-green/80">
                        {{ headline || 'Uzupełnij stanowisko' }}
                    </p>
                </div>
            </div>
            <p
                v-if="summary"
                class="mt-3 text-sm leading-relaxed text-brand-green/80"
            >
                {{ summary }}
            </p>
            <div class="mt-4 flex flex-wrap gap-1.5">
                <span
                    v-for="skill in skills.slice(0, 6)"
                    :key="skill"
                    class="rounded-full bg-brand-cream px-3 py-1 text-xs font-medium"
                >
                    {{ skill }}
                </span>
                <span
                    v-if="skills.length > 6"
                    class="rounded-full px-2 py-1 text-xs text-brand-green/80"
                >
                    +{{ skills.length - 6 }}
                </span>
                <span
                    v-if="skills.length === 0"
                    class="text-xs text-brand-green/80"
                >
                    Tu pojawią się Twoje umiejętności
                </span>
            </div>
            <p
                v-if="availableFrom"
                class="mt-4 inline-block rounded-full bg-brand-yellow px-3 py-1 text-xs font-semibold"
            >
                Dostępna od {{ formatShortDate(availableFrom, true) }}
            </p>
        </div>
        <p class="mt-4 text-xs leading-relaxed text-white/70">
            Pracodawca nie widzi zdjęcia, nazwiska ani powodu przerwy. Kontakt
            dostaje dopiero po Twojej akceptacji.
        </p>
    </section>
</template>
