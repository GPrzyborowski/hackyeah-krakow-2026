<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';
import JoinController from '@/actions/App/Http/Controllers/JobSharing/JoinController';
import { formatLongDate } from '@/components/employer/format';
import InputError from '@/components/InputError.vue';
import JobShareChip from '@/components/job-sharing/JobShareChip.vue';
import { formatHour } from '@/components/job-sharing/format';
import { Spinner } from '@/components/ui/spinner';
import { home, login, register } from '@/routes';

type JoinPreview = {
    offer: {
        id: number;
        title: string;
        company: string;
        city: string | null;
        work_mode_label: string;
        employment_fraction_label: string;
        workday_starts_at: string | null;
        workday_ends_at: string | null;
        hours_per_person: number | null;
    };
    inviter: {
        first_name: string;
        display_name: string;
    };
    expires_at: string;
};

defineProps<{
    state: 'join' | 'guest' | 'invalid';
    problem: string | null;
    token: string;
    preview: JoinPreview | null;
}>();
</script>

<template>
    <Head title="Zaproszenie do pary" />

    <div class="mx-auto flex max-w-2xl flex-col gap-5 px-4 pt-6 pb-16 sm:px-6">
        <section class="rounded-3xl bg-white p-6 sm:p-8">
            <div class="flex items-center gap-2 text-brand-green">
                <UsersRound class="size-5" />
                <p class="text-sm font-semibold">Zaproszenie do pary</p>
            </div>

            <template v-if="preview">
                <h1
                    class="mt-3 text-2xl leading-tight font-semibold tracking-tight text-brand-green sm:text-3xl"
                >
                    {{ preview.inviter.display_name }} chce aplikować z Tobą w
                    parze
                </h1>
                <p class="mt-2 text-brand-green/80">
                    <span class="font-semibold">{{ preview.offer.title }}</span>
                    · {{ preview.offer.company }}
                    <template v-if="preview.offer.city">
                        · {{ preview.offer.city }}</template
                    >
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <JobShareChip
                        :hours-per-person="preview.offer.hours_per_person"
                    />
                    <span
                        class="rounded-full bg-brand-cream px-3 py-1 text-xs font-medium text-brand-green"
                        >{{ preview.offer.employment_fraction_label }}</span
                    >
                    <span
                        class="rounded-full bg-brand-cream px-3 py-1 text-xs font-medium text-brand-green"
                        >{{ preview.offer.work_mode_label }}</span
                    >
                </div>
                <p class="mt-4 text-sm text-brand-green/80">
                    Job sharing to jeden etat podzielony między dwie osoby.
                    <template
                        v-if="
                            preview.offer.workday_starts_at &&
                            preview.offer.workday_ends_at
                        "
                        >Dzień pracy trwa od
                        {{ formatHour(preview.offer.workday_starts_at) }} do
                        {{ formatHour(preview.offer.workday_ends_at) }}, a Wy
                        same ustalacie, która bierze którą część.</template
                    >
                    Po dołączeniu otworzy się czat pary. Firma zobaczy Was
                    razem, anonimowo, dopiero gdy wyślecie jej ustalony podział
                    dnia.
                </p>
            </template>

            <h1
                v-else
                class="mt-3 text-2xl font-semibold tracking-tight text-brand-green"
            >
                Link do pary nie działa
            </h1>
        </section>

        <section
            v-if="state === 'invalid'"
            class="flex flex-col gap-3 rounded-3xl bg-brand-cream p-6 text-brand-green"
            data-test="join-problem"
        >
            <p class="text-sm font-semibold" role="alert">{{ problem }}</p>
            <Link
                :href="home()"
                class="self-start text-sm font-semibold underline underline-offset-2"
            >
                Przejdź do mumjobs
            </Link>
        </section>

        <section
            v-else-if="state === 'guest'"
            class="flex flex-col gap-3 rounded-3xl bg-brand-mint-soft p-6 text-brand-green"
        >
            <p class="text-sm">
                Żeby dołączyć, załóż konto kandydatki albo zaloguj się. Potem
                wrócisz na tę stronę.
            </p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="register({ query: { role: 'candidate' } })"
                    class="inline-flex justify-center rounded-full bg-brand-green px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft"
                    data-test="join-register"
                >
                    Załóż konto kandydatki
                </Link>
                <Link
                    :href="login()"
                    class="inline-flex justify-center rounded-full border border-brand-green px-5 py-2.5 text-sm font-semibold hover:bg-white"
                    data-test="join-login"
                >
                    Mam już konto – zaloguj się
                </Link>
            </div>
            <p v-if="preview" class="text-xs text-brand-green/80">
                Link ważny do {{ formatLongDate(preview.expires_at) }}.
            </p>
        </section>

        <Form
            v-else
            v-bind="JoinController.store.form(token)"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-3 rounded-3xl bg-brand-mint-soft p-6 text-brand-green"
        >
            <p class="text-sm">
                Po dołączeniu Twój profil zostanie oznaczony jako otwarty na job
                sharing.
            </p>
            <InputError :message="errors.join_link" />
            <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 self-start rounded-full bg-brand-green px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                :disabled="processing"
                data-test="join-pair"
            >
                <Spinner v-if="processing" />
                Dołącz do pary
            </button>
        </Form>
    </div>
</template>
