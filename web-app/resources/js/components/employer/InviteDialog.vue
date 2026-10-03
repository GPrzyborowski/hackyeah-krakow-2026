<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import InvitationController from '@/actions/App/Http/Controllers/Employer/InvitationController';
import InputError from '@/components/InputError.vue';
import {
    formatLongDate,
    formatSalaryRange,
} from '@/components/employer/format';
import type {
    AnonymousCandidate,
    SwipeOffer,
} from '@/components/employer/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        offer: SwipeOffer;
        candidate: AnonymousCandidate;
        kind?: 'invitation' | 'direct_message';
    }>(),
    { kind: 'invitation' },
);

const isDirectMessage = computed(() => props.kind === 'direct_message');

const open = defineModel<boolean>('open', { required: true });

const page = usePage();

const template = computed(() => {
    if (isDirectMessage.value) {
        return `Dzień dobry, mamy pytanie dotyczące stanowiska ${props.offer.title}. `;
    }

    const salary = formatSalaryRange(
        props.offer.salary_min,
        props.offer.salary_max,
    );
    const hours = [
        props.offer.flexible_hours
            ? 'elastyczne godziny pracy'
            : 'stałe godziny pracy',
        props.offer.fixed_meeting_hours
            ? 'spotkania w stałych godzinach, bez wieczorów'
            : null,
    ]
        .filter(Boolean)
        .join(', ');
    const companyName = page.props.auth.company?.name ?? '';

    return [
        'Dzień dobry,',
        '',
        `Twój profil bardzo pasuje do stanowiska ${props.offer.title} (${props.offer.employment_fraction_label}, ${props.offer.work_mode_label.toLowerCase()}${props.offer.city ? `, ${props.offer.city}` : ''}).`,
        salary ? `Widełki wynagrodzenia: ${salary}.` : null,
        `Godziny pracy: ${hours}.`,
        `Planowany start: ${formatLongDate(props.offer.start_date)}.`,
        '',
        'Czy masz ochotę na krótką rozmowę w przyszłym tygodniu?',
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
    const action = isDirectMessage.value
        ? InvitationController.storeDirectMessage
        : InvitationController.store;

    form.post(
        action.url({
            offer: props.offer.id,
            candidate: props.candidate.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                form.reset();
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-3xl sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="text-brand-green">
                    {{ isDirectMessage ? 'Napisz do' : 'Zaproś' }}
                    {{ candidate.anonymous_name }}
                </DialogTitle>
                <DialogDescription v-if="isDirectMessage">
                    Krótkie pytanie bez zaproszenia (do 1000 znaków). Kandydatka
                    zostaje anonimowa – jej dane zobaczysz dopiero, gdy odpowie.
                    Pytania o sytuację rodzinną są zablokowane.
                </DialogDescription>
                <DialogDescription v-else>
                    Napisz o stanowisku, widełkach i godzinach pracy. Pytania o
                    sytuację rodzinną są zablokowane.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="send">
                <label class="block text-xs font-semibold text-brand-green">
                    Wiadomość
                    <textarea
                        v-model="form.message"
                        :rows="isDirectMessage ? 5 : 10"
                        :maxlength="isDirectMessage ? 1000 : 2000"
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
                        {{
                            isDirectMessage
                                ? 'Wyślij wiadomość'
                                : 'Wyślij zaproszenie'
                        }}
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
