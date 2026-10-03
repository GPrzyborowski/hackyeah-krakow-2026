<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import { formatShortDate } from '@/components/candidate/format';

type Phase = 'pregnancy' | 'leave' | 'ready';

const props = defineProps<{
    calendar: {
        pregnancy_week: number | null;
        due_date: string | null;
        leave_starts_on: string | null;
        available_from: string | null;
        current_phase: Phase;
    };
}>();

const emit = defineEmits<{ edit: [] }>();

const phases = computed(() => [
    {
        key: 'pregnancy' as Phase,
        bar: 'bg-brand-mint',
        label: props.calendar.pregnancy_week
            ? `${props.calendar.pregnancy_week}. tydzień`
            : 'ciąża',
    },
    {
        key: 'leave' as Phase,
        bar: 'bg-brand-peach',
        label: props.calendar.leave_starts_on
            ? `urlop od ${formatShortDate(props.calendar.leave_starts_on)}`
            : 'urlop',
    },
    {
        key: 'ready' as Phase,
        bar: 'bg-brand-yellow',
        label: props.calendar.available_from
            ? `gotowa ${formatShortDate(props.calendar.available_from)}`
            : 'gotowa',
    },
]);
</script>

<template>
    <section
        class="rounded-3xl bg-brand-green p-6 text-white"
        aria-labelledby="return-calendar-heading"
    >
        <div class="flex items-center justify-between gap-2">
            <h2 id="return-calendar-heading" class="text-lg font-bold">
                Twój kalendarz powrotu
            </h2>
            <span class="inline-flex items-center gap-1 text-xs text-white/80">
                <Lock class="size-3" aria-hidden="true" /> widzisz tylko Ty
            </span>
        </div>
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
            >
                {{ phase.label }}
            </span>
        </div>
        <button
            type="button"
            class="mt-4 text-xs text-white/80 underline underline-offset-4 hover:text-white"
            @click="emit('edit')"
        >
            Zmień daty
        </button>
    </section>
</template>
