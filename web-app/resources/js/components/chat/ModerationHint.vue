<script setup lang="ts">
import { ShieldAlert } from '@lucide/vue';

defineProps<{
    reason?: string | null;
    suggestion?: string | null;
}>();
</script>

<template>
    <div
        class="flex gap-3 rounded-2xl bg-brand-yellow/60 p-4 text-sm text-brand-green"
        role="alert"
        data-test="moderation-hint"
    >
        <ShieldAlert class="mt-0.5 size-5 shrink-0" />
        <div class="space-y-1">
            <p class="font-semibold">
                Czat blokuje pytania o ciążę i plany rodzinne…
            </p>
            <p v-if="reason">{{ reason }}</p>
            <p v-if="suggestion" class="text-brand-green/80">
                {{ suggestion }}
            </p>
        </div>
    </div>
</template>
