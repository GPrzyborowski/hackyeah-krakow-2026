<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        role="img"
        aria-label="MomJobs"
        :class="className"
        v-bind="$attrs"
    >
        <circle
            cx="15"
            cy="17"
            r="13"
            class="fill-brand-green dark:fill-brand-mint"
        />
        <circle cx="25.5" cy="6.5" r="5.5" class="fill-brand-peach" />
    </svg>
</template>
