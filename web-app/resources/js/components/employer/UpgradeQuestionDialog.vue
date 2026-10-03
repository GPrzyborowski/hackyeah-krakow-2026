<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import InvitationController from '@/actions/App/Http/Controllers/Employer/InvitationController';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    offer: { id: number; title: string };
    candidate: { id: number; anonymous_name: string };
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({ message: '', from: 'invitations' });

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            form.clearErrors();
            form.message = `Dzień dobry,\n\nzapraszamy na krótką rozmowę o stanowisku ${props.offer.title}. Czy pasuje Ci termin w przyszłym tygodniu?\n\nPozdrawiamy`;
        }
    },
    { immediate: true },
);

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function send(): void {
    form.post(
        InvitationController.store.url({
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
                    Zaproś {{ candidate.anonymous_name }} do rozmowy
                </DialogTitle>
                <DialogDescription>
                    Twoje pytanie zamienimy w zaproszenie na stanowisko
                    {{ offer.title }}. Kandydatka zostaje anonimowa, dopóki go
                    nie przyjmie. Pytania o sytuację rodzinną są zablokowane.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="send">
                <label class="block text-xs font-semibold text-brand-green">
                    Wiadomość
                    <textarea
                        v-model="form.message"
                        rows="8"
                        maxlength="2000"
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
                        Wyślij zaproszenie
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
