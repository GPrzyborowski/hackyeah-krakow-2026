<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BadgeCheck, Undo2 } from '@lucide/vue';
import VerifiedCompanyBadge from '@/components/brand/VerifiedCompanyBadge.vue';
import { index } from '@/routes/admin/companies';
import { destroy, store } from '@/routes/admin/companies/verification';

type AdminCompany = {
    id: number;
    name: string;
    nip: string | null;
    city: string | null;
    members_count: number;
    offers_count: number;
    verified: boolean;
    verified_at: string | null;
    created_at: string | null;
};

type StatusFilter = 'all' | 'unverified';

defineProps<{
    status: StatusFilter;
    counts: Record<StatusFilter, number>;
    companies: AdminCompany[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Firmy', href: index() }],
    },
});

const tabs: Array<{ status: StatusFilter; label: string }> = [
    { status: 'all', label: 'Wszystkie' },
    { status: 'unverified', label: 'Do weryfikacji' },
];

const dateFormatter = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

function formatDate(value: string | null): string {
    return value ? dateFormatter.format(new Date(value)) : '–';
}
</script>

<template>
    <Head title="Weryfikacja firm" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
        <div>
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
            >
                Weryfikacja firm
            </h1>
            <p class="mt-2 text-sm text-brand-green/80">
                Sprawdź NIP w rejestrze i zweryfikuj firmę. Kandydatki zobaczą
                przy jej ofertach odznakę „Zweryfikowana firma”.
            </p>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="Filtr firm">
            <Link
                v-for="tab in tabs"
                :key="tab.status"
                :href="
                    index({
                        query: {
                            status:
                                tab.status === 'all' ? undefined : tab.status,
                        },
                    })
                "
                class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="
                    tab.status === status
                        ? 'bg-brand-green text-white'
                        : 'bg-white text-brand-green hover:bg-brand-mint-soft'
                "
                :aria-current="tab.status === status ? 'page' : undefined"
            >
                {{ tab.label }}
                <span
                    class="rounded-full px-2 text-xs"
                    :class="
                        tab.status === status ? 'bg-white/20' : 'bg-brand-cream'
                    "
                    >{{ counts[tab.status] }}</span
                >
            </Link>
        </nav>

        <div
            v-if="companies.length"
            class="overflow-x-auto rounded-3xl bg-white shadow-sm"
        >
            <table class="w-full text-left text-sm text-brand-green">
                <thead class="border-b border-brand-cream text-xs">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Firma
                        </th>
                        <th scope="col" class="px-5 py-3 font-semibold">NIP</th>
                        <th
                            scope="col"
                            class="px-5 py-3 text-right font-semibold"
                        >
                            Pracownicy
                        </th>
                        <th
                            scope="col"
                            class="px-5 py-3 text-right font-semibold"
                        >
                            Oferty
                        </th>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Status
                        </th>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Dodana
                        </th>
                        <th scope="col" class="px-5 py-3">
                            <span class="sr-only">Akcje</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="company in companies"
                        :key="company.id"
                        class="border-b border-brand-cream last:border-0"
                        :data-test="`admin-company-${company.id}`"
                    >
                        <td class="px-5 py-3">
                            <p class="font-semibold">{{ company.name }}</p>
                            <p
                                v-if="company.city"
                                class="text-xs text-brand-green/80"
                            >
                                {{ company.city }}
                            </p>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs">
                            {{ company.nip ?? '–' }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            {{ company.members_count }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            {{ company.offers_count }}
                        </td>
                        <td class="px-5 py-3">
                            <VerifiedCompanyBadge v-if="company.verified" />
                            <span
                                v-else
                                class="inline-flex rounded-full bg-brand-yellow px-3 py-1 text-xs font-semibold"
                                >Niezweryfikowana</span
                            >
                        </td>
                        <td class="px-5 py-3 text-xs whitespace-nowrap">
                            {{ formatDate(company.created_at) }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <Link
                                v-if="company.verified"
                                :href="destroy(company.id)"
                                as="button"
                                preserve-scroll
                                class="inline-flex items-center gap-1 rounded-full border border-brand-green px-4 py-1.5 text-sm font-semibold whitespace-nowrap hover:bg-brand-cream"
                            >
                                <Undo2 class="size-4" aria-hidden="true" />
                                Cofnij weryfikację
                            </Link>
                            <Link
                                v-else
                                :href="store(company.id)"
                                as="button"
                                preserve-scroll
                                class="inline-flex items-center gap-1 rounded-full bg-brand-green px-4 py-1.5 text-sm font-semibold whitespace-nowrap text-white hover:bg-brand-green-soft"
                            >
                                <BadgeCheck class="size-4" aria-hidden="true" />
                                Zweryfikuj
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-else
            class="rounded-3xl bg-white p-8 text-center text-sm text-brand-green/80"
        >
            Wszystkie firmy są zweryfikowane.
        </div>
    </div>
</template>
