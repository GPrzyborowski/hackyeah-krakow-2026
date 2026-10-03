<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { ref, watch } from 'vue';
import BrandSwitch from '@/components/candidate/BrandSwitch.vue';
import InputError from '@/components/InputError.vue';
import { privacy } from '@/routes/candidate/onboarding';

const props = defineProps<{
    hiddenFromCompanyId: number | null;
    allowDirectMessages: boolean;
    jobAlertsEnabled: boolean;
    showAvailabilityInsteadOfGap: boolean;
    careerGapNote: string | null;
    companies: { id: number; name: string }[];
}>();

const CAREER_GAP_NOTE_MAX_LENGTH = 300;

const hideFromEmployer = ref(props.hiddenFromCompanyId !== null);
const hiddenCompanyId = ref<number | null>(props.hiddenFromCompanyId);
const allowDirectMessages = ref(props.allowDirectMessages);
const jobAlertsEnabled = ref(props.jobAlertsEnabled);
const showAvailabilityInsteadOfGap = ref(props.showAvailabilityInsteadOfGap);
const gapNoteForm = useForm<{ career_gap_note: string }>({
    career_gap_note: props.careerGapNote ?? '',
});

watch(
    () => props.showAvailabilityInsteadOfGap,
    (value) => (showAvailabilityInsteadOfGap.value = value),
);

watch(
    () => props.careerGapNote,
    (value) => {
        if (!gapNoteForm.isDirty) {
            gapNoteForm.defaults({ career_gap_note: value ?? '' });
            gapNoteForm.reset();
        }
    },
);

function saveGapNote() {
    gapNoteForm.patch(privacy.url(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => gapNoteForm.defaults(),
    });
}

watch(
    () => props.allowDirectMessages,
    (value) => (allowDirectMessages.value = value),
);

watch(
    () => props.jobAlertsEnabled,
    (value) => (jobAlertsEnabled.value = value),
);

watch(
    () => props.hiddenFromCompanyId,
    (value) => {
        hiddenCompanyId.value = value;
        hideFromEmployer.value = value !== null || hideFromEmployer.value;
    },
);

function save(data: Record<string, number | boolean | null>) {
    router.patch(privacy.url(), data, {
        preserveScroll: true,
        preserveState: true,
    });
}

function onHideToggle(value: boolean) {
    if (!value) {
        hiddenCompanyId.value = null;
        save({ hidden_from_company_id: null });
    }
}
</script>

<template>
    <section class="rounded-3xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-brand-green">Prywatność</h2>
        <div class="mt-2 divide-y divide-brand-cream">
            <div>
                <BrandSwitch
                    v-model="showAvailabilityInsteadOfGap"
                    label="Pokaż datę dostępności zamiast powodu przerwy"
                    description="Włączone: firmy widzą tylko, od kiedy możesz zacząć – nigdy powodu przerwy. Wyłącz, jeśli chcesz, by firma, której zaproszenie przyjmiesz, zobaczyła Twoją notatkę o przerwie."
                    @change="
                        (value) =>
                            save({ show_availability_instead_of_gap: value })
                    "
                />
                <form class="pb-3" @submit.prevent="saveGapNote">
                    <label
                        for="career_gap_note"
                        class="flex items-center gap-1.5 text-sm font-semibold text-brand-green"
                    >
                        <Lock class="size-3.5" aria-hidden="true" />
                        Notatka o przerwie (prywatna)
                    </label>
                    <textarea
                        id="career_gap_note"
                        v-model="gapNoteForm.career_gap_note"
                        rows="2"
                        :maxlength="CAREER_GAP_NOTE_MAX_LENGTH"
                        placeholder="np. urlop macierzyński"
                        :aria-invalid="
                            gapNoteForm.errors.career_gap_note
                                ? true
                                : undefined
                        "
                        aria-describedby="career_gap_note-hint career_gap_note-error"
                        class="mt-1 w-full rounded-2xl border border-brand-mint-soft p-3 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40"
                    />
                    <p
                        id="career_gap_note-hint"
                        class="text-xs text-brand-green/80"
                    >
                        <template v-if="showAvailabilityInsteadOfGap">
                            Notatkę widzisz tylko Ty, firmy jej nie dostają.
                        </template>
                        <template v-else>
                            Po przyjęciu zaproszenia firma zobaczy: „Przerwa w
                            karierze: {{ gapNoteForm.career_gap_note || '…' }}”.
                            Przed akceptacją firma jej nie widzi.
                        </template>
                    </p>
                    <InputError
                        id="career_gap_note-error"
                        :message="gapNoteForm.errors.career_gap_note"
                    />
                    <button
                        type="submit"
                        :disabled="
                            gapNoteForm.processing || !gapNoteForm.isDirty
                        "
                        class="mt-2 rounded-full border border-brand-green px-4 py-1.5 text-xs font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-50"
                    >
                        Zapisz notatkę
                    </button>
                </form>
            </div>
            <div>
                <BrandSwitch
                    v-model="hideFromEmployer"
                    label="Ukryj profil przed obecnym pracodawcą"
                    @change="onHideToggle"
                />
                <div v-if="hideFromEmployer" class="pb-3">
                    <label for="hidden_from_company_id" class="sr-only"
                        >Obecny pracodawca</label
                    >
                    <select
                        id="hidden_from_company_id"
                        v-model="hiddenCompanyId"
                        class="w-full rounded-2xl border border-brand-mint-soft bg-white px-3 py-2 text-sm text-brand-green"
                        @change="
                            save({ hidden_from_company_id: hiddenCompanyId })
                        "
                    >
                        <option :value="null">Wybierz firmę…</option>
                        <option
                            v-for="company in companies"
                            :key="company.id"
                            :value="company.id"
                        >
                            {{ company.name }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-brand-green/80">
                        Ta firma w ogóle nie zobaczy Twojego profilu.
                    </p>
                </div>
            </div>
            <BrandSwitch
                v-model="allowDirectMessages"
                label="Pozwól firmom pisać bez zaproszenia"
                description="Firma może zadać Ci krótkie pytanie, nadal nie znając Twoich danych. Ujawnisz je dopiero, gdy odpowiesz."
                @change="(value) => save({ allow_direct_messages: value })"
            />
            <BrandSwitch
                v-model="jobAlertsEnabled"
                label="Wysyłaj mi nowe dopasowane oferty"
                description="Raz w tygodniu e-mail z maksymalnie 5 nowymi ofertami, które pasują do Ciebie w co najmniej 60% i w których zdążysz zacząć."
                @change="(value) => save({ job_alerts_enabled: value })"
            />
        </div>
    </section>
</template>
