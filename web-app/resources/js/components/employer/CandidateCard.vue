<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { formatShortDate } from '@/components/employer/format';
import type { AnonymousCandidate } from '@/components/employer/types';

defineProps<{
    candidate: AnonymousCandidate;
}>();

function experienceLabel(years: number | null): string | null {
    if (years === null) {
        return null;
    }

    if (years === 1) {
        return '1 rok doświadczenia';
    }

    const lastDigit = years % 10;
    const lastTwoDigits = years % 100;
    const isFew =
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14);

    return `${years} ${isFew ? 'lata' : 'lat'} doświadczenia`;
}
</script>

<template>
    <article
        class="relative rounded-3xl bg-white p-6 shadow-[0_14px_0_-4px_var(--color-brand-mint-soft)] sm:p-8"
        data-test="candidate-card"
    >
        <div class="flex items-start gap-4">
            <div
                class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-peach text-xl font-bold text-brand-green"
                aria-hidden="true"
            >
                {{ candidate.initial }}
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-bold text-brand-green">
                    {{ candidate.anonymous_name }}
                </h2>
                <p class="text-sm text-brand-green/80">
                    {{
                        [
                            candidate.headline,
                            experienceLabel(candidate.years_of_experience),
                        ]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </p>
            </div>
            <span
                v-if="candidate.match"
                class="shrink-0 rounded-full bg-brand-yellow px-3 py-1 text-sm font-bold text-brand-green"
            >
                {{ candidate.match.score }}%
            </span>
        </div>

        <p
            v-if="candidate.is_interested"
            class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-brand-peach/40 px-3 py-1 text-xs font-semibold text-brand-green"
        >
            <Sparkles class="size-3.5" /> Zainteresowana Twoją ofertą
        </p>

        <div
            v-if="candidate.ai_summary"
            class="mt-5 rounded-2xl bg-brand-cream p-4"
        >
            <p class="text-xs font-semibold text-brand-green">
                Podsumowanie profilu (AI)
            </p>
            <p class="mt-1 text-sm leading-relaxed text-brand-green/90">
                {{ candidate.ai_summary }}
            </p>
        </div>

        <div class="mt-5">
            <p class="text-xs font-semibold text-brand-green">Umiejętności</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="skill in candidate.skills"
                    :key="skill.name"
                    class="rounded-full px-3 py-1 text-xs font-medium"
                    :class="
                        skill.matched
                            ? 'bg-brand-green text-white'
                            : 'border border-brand-green/25 text-brand-green'
                    "
                >
                    {{ skill.name }}
                </span>
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div class="rounded-2xl border border-brand-green/15 p-3">
                <dt class="text-[11px] text-brand-green/80">Dostępna od</dt>
                <dd class="text-sm font-bold text-brand-green">
                    {{ formatShortDate(candidate.available_from) }}
                </dd>
            </div>
            <div class="rounded-2xl border border-brand-green/15 p-3">
                <dt class="text-[11px] text-brand-green/80">Wymiar</dt>
                <dd class="text-sm font-bold text-brand-green">
                    {{ candidate.employment_fractions.join(', ') || '—' }}
                </dd>
            </div>
            <div class="rounded-2xl border border-brand-green/15 p-3">
                <dt class="text-[11px] text-brand-green/80">Tryb</dt>
                <dd class="text-sm font-bold text-brand-green">
                    {{ candidate.work_modes.join(', ') || '—' }}
                </dd>
            </div>
        </dl>

        <p class="mt-5 text-xs text-brand-green/80">
            Zdjęcie, nazwisko i dane kontaktowe zobaczysz po jej akceptacji
            zaproszenia.
        </p>
    </article>
</template>
