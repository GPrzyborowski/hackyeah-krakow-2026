<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import { formatShortDate } from '@/components/candidate/format';
import type {
    ReturnCalendar,
    ReturnCalendarPhase,
} from '@/components/candidate/types';

const props = withDefaults(
    defineProps<{
        calendar: ReturnCalendar;
        editLabel?: string;
    }>(),
    { editLabel: 'Zmień daty' },
);

const emit = defineEmits<{ edit: [] }>();

const bars: Record<ReturnCalendarPhase, string> = {
    pregnancy: 'bg-brand-mint',
    leave: 'bg-brand-peach',
    return: 'bg-brand-mint',
    ready: 'bg-brand-yellow',
};

function labelFor(phase: ReturnCalendarPhase): string {
    const { calendar } = props;

    switch (phase) {
        case 'pregnancy':
            return calendar.pregnancy_week
                ? `${calendar.pregnancy_week}. tydzień`
                : 'ciąża';
        case 'leave':
            return calendar.leave_starts_on
                ? `urlop od ${formatShortDate(calendar.leave_starts_on)}`
                : 'urlop';
        case 'return':
            return 'powrót';
        case 'ready':
            return calendar.available_from
                ? `gotowa ${formatShortDate(calendar.available_from)}`
                : 'gotowa';
    }
}

const phases = computed(() =>
    props.calendar.phases.map((key) => ({
        key,
        bar: bars[key],
        label: labelFor(key),
    })),
);
</script>

<template>
    <section
        class="rounded-3xl bg-brand-green p-6 text-white"
        aria-labelledby="return-calendar-heading"
        data-test="return-calendar"
    >
        <div class="flex items-center justify-between gap-2">
            <h2 id="return-calendar-heading" class="text-lg font-bold">
                Twój kalendarz powrotu
            </h2>
            <span class="inline-flex items-center gap-1 text-xs text-white/80">
                <Lock class="size-3" aria-hidden="true" /> widzisz tylko Ty
            </span>
        </div>
        <p v-if="calendar.stage_label" class="mt-1 text-sm text-white/80">
            {{ calendar.stage_label }}
        </p>
        <div class="mt-4 grid grid-cols-3 gap-1.5">
            <div
                v-for="phase in phases"
                :key="phase.key"
                class="h-3 rounded-full"
                :class="[
                    phase.bar,
                    calendar.current_phase === phase.key
                        ? 'ring-2 ring-white ring-offset-2 ring-offset-brand-green'
                        : 'opacity-80',
                ]"
            />
        </div>
        <div
            class="mt-3 grid grid-cols-3 gap-1.5 text-xs text-white/80 sm:text-sm"
        >
            <span
                v-for="(phase, position) in phases"
                :key="phase.key"
                :class="{
                    'text-center': position === 1,
                    'text-right': position === 2,
                    'font-semibold text-white':
                        calendar.current_phase === phase.key,
                }"
                :aria-current="
                    calendar.current_phase === phase.key ? 'step' : undefined
                "
            >
                {{ phase.label }}
            </span>
        </div>
        <button
            type="button"
            class="mt-4 text-xs text-white/80 underline underline-offset-4 hover:text-white"
            @click="emit('edit')"
        >
            {{ editLabel }}
        </button>
    </section>
</template>
