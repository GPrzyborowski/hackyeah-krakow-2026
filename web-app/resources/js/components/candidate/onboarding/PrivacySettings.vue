<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarCheck } from '@lucide/vue';
import { ref, watch } from 'vue';
import BrandSwitch from '@/components/candidate/BrandSwitch.vue';
import { privacy } from '@/routes/candidate/onboarding';

const props = defineProps<{
    hiddenFromCompanyId: number | null;
    companies: { id: number; name: string }[];
}>();

const hideFromEmployer = ref(props.hiddenFromCompanyId !== null);
const hiddenCompanyId = ref<number | null>(props.hiddenFromCompanyId);

watch(
    () => props.hiddenFromCompanyId,
    (value) => {
        hiddenCompanyId.value = value;
        hideFromEmployer.value = value !== null || hideFromEmployer.value;
    },
);

function save(data: Record<string, number | null>) {
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
            <p class="flex items-start gap-2 py-3 text-sm text-brand-green">
                <CalendarCheck class="mt-0.5 size-4 shrink-0" />
                Pracodawcy widzą tylko datę, od kiedy możesz zacząć – nigdy
                powodu przerwy.
            </p>
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
                    <p class="mt-1 text-xs text-brand-green/60">
                        Ta firma w ogóle nie zobaczy Twojego profilu.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
