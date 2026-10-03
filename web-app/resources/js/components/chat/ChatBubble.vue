<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        mine: boolean;
        meta?: string | null;
        tone?: 'default' | 'peach' | 'yellow';
    }>(),
    { meta: null, tone: 'default' },
);

const toneClass = computed(() => {
    if (props.tone === 'peach') {
        return 'bg-brand-peach text-brand-green';
    }

    if (props.tone === 'yellow') {
        return 'bg-brand-yellow text-brand-green';
    }

    return props.mine
        ? 'bg-brand-green text-white'
        : 'bg-white text-brand-green shadow-sm';
});
</script>

<template>
    <div class="flex flex-col" :class="mine ? 'items-end' : 'items-start'">
        <div
            class="max-w-[85%] rounded-3xl px-4 py-3 text-sm leading-relaxed break-words whitespace-pre-line md:max-w-[75%]"
            :class="[mine ? 'rounded-br-lg' : 'rounded-bl-lg', toneClass]"
        >
            <slot />
        </div>
        <span v-if="meta" class="mt-1 px-2 text-[11px] text-brand-green/50">
            {{ meta }}
        </span>
    </div>
</template>
