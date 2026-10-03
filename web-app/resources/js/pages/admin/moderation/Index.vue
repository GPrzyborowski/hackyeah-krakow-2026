<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { index } from '@/routes/admin/moderation';

type ModerationEventRow = {
    id: number;
    context: string;
    context_label: string;
    company: { id: number; name: string } | null;
    author_name: string | null;
    excerpt: string | null;
    reason: string | null;
    moderator: 'keyword' | 'claude';
    created_at: string | null;
};

type Offender = {
    company: { id: number; name: string };
    total: number;
    last_at: string | null;
};

const props = defineProps<{
    filters: { context: string | null; company: number | null };
    total: number;
    contexts: Array<{ value: string; label: string }>;
    companies: Array<{ id: number; name: string }>;
    offenders: Offender[];
    events: ModerationEventRow[];
    retention_days: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Zablokowane wiadomości', href: index() }],
    },
});

const dateFormatter = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function formatDate(value: string | null): string {
    return value ? dateFormatter.format(new Date(value)) : '–';
}

function filterUrl(filters: {
    context?: string | null;
    company?: number | null;
}): string {
    const next = { ...props.filters, ...filters };

    return index.url({
        query: {
            context: next.context ?? undefined,
            company: next.company ?? undefined,
        },
    });
}

function changeCompany(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    router.get(
        filterUrl({ company: value === '' ? null : Number(value) }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Zablokowane wiadomości" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
        <div>
            <h1
                class="text-3xl font-extrabold tracking-tight text-brand-green md:text-5xl"
            >
                Zablokowane wiadomości
            </h1>
            <p class="mt-2 text-sm text-brand-green/80">
                Teksty pracodawców zatrzymane przez moderację (pytania o ciążę,
                dzieci, plany rodzinne). Nie zostały zapisane ani wysłane.
                Fragmenty opisów kandydatek nie są przechowywane. Wpisy starsze
                niż {{ retention_days }} dni są usuwane automatycznie.
            </p>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="Rodzaj tekstu">
            <Link
                v-for="option in [
                    { value: null, label: 'Wszystkie' },
                    ...contexts,
                ]"
                :key="option.value ?? 'all'"
                :href="filterUrl({ context: option.value })"
                preserve-scroll
                class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="
                    option.value === filters.context
                        ? 'bg-brand-green text-white'
                        : 'bg-white text-brand-green hover:bg-brand-mint-soft'
                "
                :aria-current="
                    option.value === filters.context ? 'page' : undefined
                "
            >
                {{ option.label }}
            </Link>
        </nav>

        <label class="flex items-center gap-2 text-sm text-brand-green/80">
            Firma
            <select
                :value="filters.company ?? ''"
                class="rounded-full border border-brand-mint-soft bg-white px-3 py-1.5 text-sm text-brand-green"
                data-test="company-filter"
                @change="changeCompany"
            >
                <option value="">Wszystkie firmy</option>
                <option
                    v-for="company in companies"
                    :key="company.id"
                    :value="company.id"
                >
                    {{ company.name }}
                </option>
            </select>
        </label>

        <section
            v-if="offenders.length"
            class="rounded-3xl bg-white p-6 shadow-sm"
            aria-labelledby="offenders-heading"
        >
            <h2
                id="offenders-heading"
                class="inline-flex items-center gap-2 text-lg font-bold text-brand-green"
            >
                <ShieldAlert class="size-5" aria-hidden="true" />
                Firmy z największą liczbą blokad
            </h2>
            <ul class="mt-4 flex flex-wrap gap-2">
                <li v-for="offender in offenders" :key="offender.company.id">
                    <Link
                        :href="filterUrl({ company: offender.company.id })"
                        preserve-scroll
                        class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-brand-green transition hover:bg-brand-mint-soft"
                        :class="
                            offender.total >= 3
                                ? 'bg-brand-peach'
                                : 'bg-brand-cream'
                        "
                        :title="`Ostatnia blokada: ${formatDate(offender.last_at)}`"
                    >
                        {{ offender.company.name }}
                        <span class="rounded-full bg-white/70 px-2 text-xs">{{
                            offender.total
                        }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <p class="text-sm text-brand-green/80">
            Wyników: <strong>{{ total }}</strong>
            <template v-if="total > events.length">
                (pokazano {{ events.length }} najnowszych)</template
            >
        </p>

        <div
            v-if="events.length"
            class="overflow-x-auto rounded-3xl bg-white shadow-sm"
        >
            <table class="w-full text-left text-sm text-brand-green">
                <thead class="border-b border-brand-cream text-xs">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Data
                        </th>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Firma
                        </th>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Rodzaj
                        </th>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Fragment
                        </th>
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Powód
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="event in events"
                        :key="event.id"
                        class="border-b border-brand-cream align-top last:border-0"
                        :data-test="`moderation-event-${event.id}`"
                    >
                        <td class="px-5 py-3 text-xs whitespace-nowrap">
                            {{ formatDate(event.created_at) }}
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-semibold">
                                {{ event.company?.name ?? '–' }}
                            </p>
                            <p
                                v-if="event.author_name"
                                class="text-xs text-brand-green/80"
                            >
                                {{ event.author_name }}
                            </p>
                        </td>
                        <td class="px-5 py-3">
                            <span
                                class="inline-flex rounded-full bg-brand-mint-soft px-3 py-1 text-xs font-semibold whitespace-nowrap"
                                >{{ event.context_label }}</span
                            >
                            <p class="mt-1 text-xs text-brand-green/80">
                                {{
                                    event.moderator === 'claude'
                                        ? 'AI (Claude)'
                                        : 'Słowa kluczowe'
                                }}
                            </p>
                        </td>
                        <td class="max-w-sm px-5 py-3">
                            <q v-if="event.excerpt" class="italic">{{
                                event.excerpt
                            }}</q>
                            <span v-else class="text-xs text-brand-green/80"
                                >Treść niezapisana (tekst kandydatki)</span
                            >
                        </td>
                        <td class="max-w-xs px-5 py-3 text-xs">
                            {{ event.reason ?? '–' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-else
            class="rounded-3xl bg-white p-8 text-center text-sm text-brand-green/80"
        >
            Brak zablokowanych wiadomości dla wybranych filtrów.
        </div>
    </div>
</template>
