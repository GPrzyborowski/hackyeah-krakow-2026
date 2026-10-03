<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import { destroy, store } from '@/routes/candidate/offers/interest';

const { offerId, isInterested } = defineProps<{
    offerId: number;
    isInterested: boolean;
}>();

const processing = ref(false);

function toggle() {
    router.visit(isInterested ? destroy(offerId) : store(offerId), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <button
        type="button"
        :disabled="processing"
        class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-60"
        :class="
            isInterested
                ? 'bg-brand-mint-soft text-brand-green hover:bg-brand-mint/40'
                : 'bg-brand-green text-white hover:bg-brand-green-soft'
        "
        :aria-pressed="isInterested"
        @click="toggle"
    >
        <Check v-if="isInterested" class="size-4" />
        {{ isInterested ? 'Zainteresowana' : 'Pokaż zainteresowanie' }}
    </button>
</template>
