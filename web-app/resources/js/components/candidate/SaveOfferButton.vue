<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bookmark, BookmarkCheck } from '@lucide/vue';
import { ref } from 'vue';
import { save, unsave } from '@/routes/candidate/offers';

const { offerId, isSaved } = defineProps<{
    offerId: number;
    isSaved: boolean;
}>();

const processing = ref(false);

function toggle() {
    router.visit(isSaved ? unsave(offerId) : save(offerId), {
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
        class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-60"
        :class="
            isSaved
                ? 'border-brand-mint bg-brand-mint-soft text-brand-green hover:bg-brand-mint/40'
                : 'border-brand-green text-brand-green hover:bg-brand-cream'
        "
        :aria-pressed="isSaved"
        data-test="save-offer-button"
        @click="toggle"
    >
        <BookmarkCheck v-if="isSaved" class="size-4" />
        <Bookmark v-else class="size-4" />
        {{ isSaved ? 'Zapisano' : 'Zapisz' }}
    </button>
</template>
