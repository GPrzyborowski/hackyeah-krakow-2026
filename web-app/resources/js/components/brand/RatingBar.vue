<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    value: number | null;
}>();

const width = computed(() =>
    props.value === null ? 0 : Math.round((props.value / 5) * 100),
);
</script>

<template>
    <div>
        <div class="flex items-center justify-between text-xs">
            <span class="text-brand-green/80">{{ label }}</span>
            <span v-if="value !== null" class="sr-only">{{ value }} / 5</span>
        </div>
        <div
            class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-brand-cream"
        >
            <div
                class="h-full rounded-full bg-brand-green"
                :style="{ width: `${width}%` }"
            />
        </div>
    </div>
</template>
