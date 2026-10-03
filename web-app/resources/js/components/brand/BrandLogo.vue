<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{ inverted?: boolean; class?: HTMLAttributes['class'] }>(),
    { inverted: false, class: undefined },
);
</script>

<template>
    <img
        :src="props.inverted ? '/images/logo-white.png' : '/images/logo.png'"
        alt="mumjobs"
        width="366"
        height="120"
        :class="cn('block h-10 w-auto shrink-0', props.class)"
    />
</template>
