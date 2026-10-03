<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import EmployerPairController from '@/actions/App/Http/Controllers/JobSharing/EmployerPairController';
import InputError from '@/components/InputError.vue';
import {
    formatLongDate,
    formatSalaryRange,
} from '@/components/employer/format';
import { formatHour } from '@/components/job-sharing/format';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    pairId: number;
    memberNames: string[];
    offer: {
        title: string;
        city: string | null;
        start_date: string;
        salary_min: number | null;
        salary_max: number | null;
        employment_fraction_label: string;
        work_mode_label: string;
        workday_starts_at: string;
        workday_ends_at: string;
    };
    scheduleSummary: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage();

const template = computed(() => {
    const salary = formatSalaryRange(
        props.offer.salary_min,
        props.offer.salary_max,
    );
    const companyName = page.props.auth.company?.name ?? '';

    return [
        'Dzień dobry,',
        '',
        `Wasza para bardzo pasuje do stanowiska ${props.offer.title} w modelu job sharing (${props.offer.employment_fraction_label} dla każdej z Was, ${props.offer.work_mode_label.toLowerCase()}${props.offer.city ? `, ${props.offer.city}` : ''}).`,
        `Dzień pracy ${formatHour(props.offer.workday_starts_at)}–${formatHour(props.offer.workday_ends_at)}, proponowany podział: ${props.scheduleSummary}.`,
        salary ? `Widełki wynagrodzenia: ${salary}.` : null,
        `Planowany start: ${formatLongDate(props.offer.start_date)}.`,
        '',
        'Czy macie ochotę na wspólną rozmowę w przyszłym tygodniu?',
        '',
        `Pozdrawiamy${companyName ? `,\n${companyName}` : ''}`,
    ]
        .filter((line) => line !== null)
        .join('\n');
});

const form = useForm({ message: '' });

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            form.clearErrors();
            form.message = template.value;
        }
    },
    { immediate: true },
);

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function send(): void {
    form.post(EmployerPairController.invite.url(props.pairId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-3xl sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="text-brand-green">
                    Zaproś parę: {{ memberNames.join(' i ') }}
                </DialogTitle>
                <DialogDescription>
                    Każda z osób dostanie to zaproszenie i odpowie na nie
                    osobno. Pytania o sytuację rodzinną są zablokowane.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="send">
                <label class="block text-xs font-semibold text-brand-green">
                    Wiadomość
                    <textarea
                        v-model="form.message"
                        rows="10"
                        class="mt-1.5 w-full rounded-2xl border border-brand-green/60 bg-white px-4 py-3 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40"
                        :aria-invalid="Boolean(form.errors.message)"
                    />
                </label>

                <div
                    v-if="errors.message_suggestion"
                    class="rounded-2xl bg-brand-yellow/60 p-4 text-sm text-brand-green"
                    role="alert"
                >
                    <p class="flex items-center gap-2 font-semibold">
                        <ShieldAlert class="size-4" />
                        {{ form.errors.message }}
                    </p>
                    <p class="mt-1">{{ errors.message_suggestion }}</p>
                </div>
                <InputError v-else :message="form.errors.message" />

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="h-10 rounded-full px-4 text-sm font-medium text-brand-green hover:bg-brand-mint-soft"
                        @click="open = false"
                    >
                        Anuluj
                    </button>
                    <button
                        type="submit"
                        class="h-10 rounded-full bg-brand-green px-5 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Wyślij zaproszenie do pary
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
