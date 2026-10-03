<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import OfferMatchPreviewController from '@/actions/App/Http/Controllers/Employer/OfferMatchPreviewController';
import { formatLongDate } from '@/components/employer/format';

type PreviewCounts = { with_required: number; with_nice_to_have: number };

const props = defineProps<{
    startDate: string;
    requiredSkills: string[];
    niceToHaveSkills: string[];
}>();

const counts = ref<PreviewCounts | null>(null);

const preview = useHttp<
    {
        start_date: string;
        required_skills: string[];
        nice_to_have_skills: string[];
    },
    PreviewCounts
>({ start_date: '', required_skills: [], nice_to_have_skills: [] });

const refresh = useDebounceFn(async () => {
    if (!props.startDate) {
        counts.value = null;

        return;
    }

    preview.start_date = props.startDate;
    preview.required_skills = [...props.requiredSkills];
    preview.nice_to_have_skills = [...props.niceToHaveSkills];

    try {
        counts.value = await preview.post(OfferMatchPreviewController.url());
    } catch {
        counts.value = null;
    }
}, 350);

watch(
    () => [props.startDate, props.requiredSkills, props.niceToHaveSkills],
    () => void refresh(),
    { deep: true },
);

onMounted(() => void refresh());

const niceRatio = computed(() => {
    if (!counts.value || counts.value.with_required === 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round(
            (counts.value.with_nice_to_have / counts.value.with_required) * 100,
        ),
    );
});
</script>

<template>
    <section
        class="rounded-3xl bg-brand-green p-6 text-white shadow-sm"
        aria-live="polite"
    >
        <h2 class="text-lg font-semibold">Pasujące kandydatki</h2>

        <template v-if="counts">
            <div class="mt-3 flex items-center gap-3">
                <span
                    class="text-5xl leading-none font-bold text-brand-yellow tabular-nums"
                    :class="{ 'opacity-60': preview.processing }"
                >
                    {{ counts.with_required }}
                </span>
                <p class="text-sm text-white/80">
                    osób zna te umiejętności i może zacząć około
                    {{ formatLongDate(startDate) }}
                </p>
            </div>

            <div class="mt-5 space-y-3 text-xs">
                <div>
                    <div class="flex justify-between">
                        <span class="text-white/80">Z tagami wymaganymi</span>
                        <span class="font-semibold">{{
                            counts.with_required
                        }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full bg-brand-peach transition-all"
                            :style="{
                                width: counts.with_required > 0 ? '100%' : '0%',
                            }"
                        />
                    </div>
                </div>
                <div>
                    <div class="flex justify-between">
                        <span class="text-white/80"
                            >Z tagami mile widzianymi</span
                        >
                        <span class="font-semibold">{{
                            counts.with_nice_to_have
                        }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full bg-brand-peach transition-all"
                            :style="{ width: `${niceRatio}%` }"
                        />
                    </div>
                </div>
            </div>
        </template>
        <div v-else class="mt-4 space-y-2">
            <div
                v-if="preview.processing"
                class="h-10 w-20 animate-pulse rounded-lg bg-white/10"
            />
            <p class="text-sm text-white/70">
                Ustaw planowany start i dodaj tagi, a policzymy pasujące
                kandydatki.
            </p>
        </div>

        <p class="mt-4 text-xs text-white/60">
            Liczby zmieniają się na żywo, gdy dodajesz lub usuwasz tagi.
        </p>
    </section>
</template>
