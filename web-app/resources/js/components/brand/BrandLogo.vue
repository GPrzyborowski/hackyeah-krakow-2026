<script setup lang="ts">
withDefaults(defineProps<{ inverted?: boolean }>(), { inverted: false });
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 32 32"
            class="size-7 shrink-0"
            aria-hidden="true"
        >
            <circle
                cx="15"
                cy="17"
                r="13"
                :class="inverted ? 'fill-white' : 'fill-brand-green'"
            />
            <circle cx="25.5" cy="6.5" r="5.5" class="fill-brand-peach" />
        </svg>
        <span
            class="text-xl font-semibold tracking-tight"
            :class="inverted ? 'text-white' : 'text-brand-green'"
            >MomJobs</span
        >
    </span>
</template>
