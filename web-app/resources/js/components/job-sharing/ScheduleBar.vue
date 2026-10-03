<script setup lang="ts">
import { computed } from 'vue';
import { formatHour, toMinutes } from '@/components/job-sharing/format';
import type { ScheduleBarBlock } from '@/components/job-sharing/types';

const props = defineProps<{
    workdayStartsAt: string;
    workdayEndsAt: string;
    blocks: ScheduleBarBlock[];
}>();

const start = computed(() => toMinutes(props.workdayStartsAt));
const length = computed(() =>
    Math.max(1, toMinutes(props.workdayEndsAt) - start.value),
);

function percent(time: string): number {
    return Math.min(
        100,
        Math.max(0, ((toMinutes(time) - start.value) / length.value) * 100),
    );
}

const segments = computed(() =>
    [...props.blocks]
        .sort((first, second) =>
            first.starts_at.localeCompare(second.starts_at),
        )
        .map((block) => ({
            ...block,
            left: percent(block.starts_at),
            width: Math.max(
                0,
                percent(block.ends_at) - percent(block.starts_at),
            ),
        })),
);

const ticks = computed(() => {
    const times = new Set<string>([props.workdayStartsAt.slice(0, 5)]);

    segments.value.forEach((segment) => times.add(segment.ends_at.slice(0, 5)));
    times.add(props.workdayEndsAt.slice(0, 5));

    return [...times].map((time) => ({ time, left: percent(time) }));
});
</script>

<template>
    <div class="w-full" data-test="schedule-bar">
        <ul class="sr-only">
            <li v-for="segment in segments" :key="segment.key">
                {{ segment.label }}: {{ formatHour(segment.starts_at) }}–{{
                    formatHour(segment.ends_at)
                }}
            </li>
        </ul>
        <div
            class="relative h-4 text-[11px] font-semibold text-brand-green/80"
            aria-hidden="true"
        >
            <span
                v-for="tick in ticks"
                :key="tick.time"
                class="absolute -translate-x-1/2 first:translate-x-0 last:-translate-x-full"
                :style="{ left: `${tick.left}%` }"
                >{{ formatHour(tick.time) }}</span
            >
        </div>
        <div
            class="relative mt-2 h-9 overflow-hidden rounded-full bg-brand-cream text-xs font-medium text-brand-green"
            aria-hidden="true"
        >
            <div
                v-for="segment in segments"
                :key="segment.key"
                class="absolute inset-y-0 flex items-center truncate border-x-2 border-white px-3 first:border-l-0 last:border-r-0"
                :class="
                    segment.tone === 'peach'
                        ? 'bg-brand-peach'
                        : 'bg-brand-yellow'
                "
                :title="`${segment.label}: ${formatHour(segment.starts_at)}–${formatHour(segment.ends_at)}`"
                :style="{
                    left: `${segment.left}%`,
                    width: `${segment.width}%`,
                }"
            >
                {{ segment.label }}
            </div>
        </div>
    </div>
</template>
