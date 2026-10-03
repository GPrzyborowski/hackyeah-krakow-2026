<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Clock, Mail, UserMinus, X } from '@lucide/vue';
import CompanyController from '@/actions/App/Http/Controllers/Employer/CompanyController';
import CompanyTeamController from '@/actions/App/Http/Controllers/Employer/CompanyTeamController';
import CompanyTabs from '@/components/employer/CompanyTabs.vue';
import { formatShortDate } from '@/components/employer/format';
import InputError from '@/components/InputError.vue';

type Member = {
    id: number;
    name: string;
    email: string;
    joined_at: string;
    is_current_user: boolean;
};

type TeamInvitation = {
    id: number;
    email: string;
    invited_by: string | null;
    created_at: string;
    expires_at: string;
    is_expired: boolean;
};

defineProps<{
    company: { id: number; name: string };
    members: Member[];
    invitations: TeamInvitation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Firma', href: CompanyController.edit() },
            { title: 'Zespół', href: CompanyTeamController.index() },
        ],
    },
});

const fieldClass =
    'mt-1.5 h-11 w-full rounded-2xl border border-brand-line bg-white px-4 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40';
</script>

<template>
    <Head title="Zespół firmy" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
        <CompanyTabs active="team" class="mb-5" />
        <h1 class="text-3xl font-bold text-brand-green sm:text-4xl">
            Zespół rekrutacyjny
        </h1>
        <p class="mt-2 text-sm text-brand-green/80">
            Osoby z zespołu widzą oferty, kandydatki i rozmowy firmy
            {{ company.name }} i mogą zapraszać kolejne osoby.
        </p>

        <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section
                class="rounded-3xl bg-white p-6 shadow-sm"
                aria-labelledby="members-heading"
            >
                <h2
                    id="members-heading"
                    class="text-xl font-semibold text-brand-green"
                >
                    Członkowie zespołu ({{ members.length }})
                </h2>
                <ul class="mt-4 divide-y divide-brand-line">
                    <li
                        v-for="member in members"
                        :key="member.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold text-brand-green">
                                {{ member.name }}
                                <span
                                    v-if="member.is_current_user"
                                    class="ml-1 rounded-full bg-brand-mint-soft px-2 py-0.5 text-xs font-medium"
                                    >to Ty</span
                                >
                            </p>
                            <p class="truncate text-sm text-brand-green/80">
                                {{ member.email }} · w zespole od
                                {{ formatShortDate(member.joined_at) }}
                            </p>
                        </div>
                        <Form
                            v-if="!member.is_current_user"
                            v-bind="
                                CompanyTeamController.destroyMember.form(
                                    member.id,
                                )
                            "
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <button
                                type="submit"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full border border-brand-line px-4 text-sm font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-50"
                                :disabled="processing"
                                :aria-label="`Usuń z zespołu: ${member.name}`"
                            >
                                <UserMinus class="size-4" aria-hidden="true" />
                                Usuń
                            </button>
                        </Form>
                    </li>
                </ul>

                <h2 class="mt-8 text-xl font-semibold text-brand-green">
                    Oczekujące zaproszenia
                </h2>
                <p
                    v-if="invitations.length === 0"
                    class="mt-3 text-sm text-brand-green/80"
                >
                    Brak oczekujących zaproszeń.
                </p>
                <ul v-else class="mt-4 divide-y divide-brand-line">
                    <li
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3"
                    >
                        <div class="min-w-0">
                            <p
                                class="flex items-center gap-2 truncate font-semibold text-brand-green"
                            >
                                <Mail
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ invitation.email }}
                            </p>
                            <p
                                class="mt-0.5 flex items-center gap-1.5 text-sm text-brand-green/80"
                            >
                                <Clock class="size-3.5" aria-hidden="true" />
                                <span v-if="invitation.is_expired"
                                    >Wygasło
                                    {{ formatShortDate(invitation.expires_at) }}
                                    – wyślij ponownie</span
                                >
                                <span v-else
                                    >Ważne do
                                    {{
                                        formatShortDate(invitation.expires_at)
                                    }}</span
                                >
                                <span v-if="invitation.invited_by"
                                    >· zaprosił(a)
                                    {{ invitation.invited_by }}</span
                                >
                            </p>
                        </div>
                        <Form
                            v-bind="
                                CompanyTeamController.destroyInvitation.form(
                                    invitation.id,
                                )
                            "
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <button
                                type="submit"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full border border-brand-line px-4 text-sm font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-50"
                                :disabled="processing"
                                :aria-label="`Anuluj zaproszenie dla ${invitation.email}`"
                            >
                                <X class="size-4" aria-hidden="true" />
                                Anuluj
                            </button>
                        </Form>
                    </li>
                </ul>
            </section>

            <aside>
                <Form
                    v-bind="CompanyTeamController.storeInvitation.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    class="space-y-4 rounded-3xl bg-white p-6 text-brand-green shadow-sm"
                    v-slot="{ errors, processing }"
                >
                    <h2 class="text-lg font-semibold">Zaproś rekrutera</h2>
                    <p class="text-sm text-brand-green/80">
                        Wyślemy e-mail z linkiem ważnym 7 dni. Po kliknięciu
                        osoba założy konto albo dołączy istniejącym kontem
                        pracodawcy.
                    </p>
                    <label class="block text-xs font-semibold">
                        Adres e-mail
                        <input
                            name="email"
                            type="email"
                            autocomplete="off"
                            required
                            :aria-invalid="errors.email ? true : undefined"
                            aria-describedby="invite-email-error"
                            :class="fieldClass"
                            placeholder="rekruterka@firma.pl"
                        />
                    </label>
                    <InputError
                        id="invite-email-error"
                        :message="errors.email"
                    />
                    <button
                        type="submit"
                        class="h-11 w-full rounded-full bg-brand-green px-6 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                        :disabled="processing"
                    >
                        Wyślij zaproszenie
                    </button>
                </Form>
            </aside>
        </div>
    </div>
</template>
