<script setup lang="ts">
withDefaults(
    defineProps<{
        mine: boolean;
        meta?: string | null;
    }>(),
    { meta: null },
);
</script>

<template>
    <div class="flex flex-col" :class="mine ? 'items-end' : 'items-start'">
        <div
            class="max-w-[85%] rounded-3xl px-4 py-3 text-sm leading-relaxed whitespace-pre-line md:max-w-[75%]"
            :class="
                mine
                    ? 'rounded-br-lg bg-brand-green text-white'
                    : 'rounded-bl-lg bg-white text-brand-green shadow-sm'
            "
        >
            <slot />
        </div>
        <span v-if="meta" class="mt-1 px-2 text-[11px] text-brand-green/50">
            {{ meta }}
        </span>
    </div>
</template>
