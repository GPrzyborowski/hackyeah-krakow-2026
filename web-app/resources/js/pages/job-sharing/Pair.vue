<script setup lang="ts">
import { Form, Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    CircleDashed,
    SendHorizontal,
    ShieldCheck,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import PairController from '@/actions/App/Http/Controllers/JobSharing/PairController';
import PairMessageController from '@/actions/App/Http/Controllers/JobSharing/PairMessageController';
import PairScheduleController from '@/actions/App/Http/Controllers/JobSharing/PairScheduleController';
import Chip from '@/components/candidate/Chip.vue';
import ChatBubble from '@/components/chat/ChatBubble.vue';
import { formatBubbleTime } from '@/components/chat/format';
import InputError from '@/components/InputError.vue';
import { formatHour } from '@/components/job-sharing/format';
import ScheduleBar from '@/components/job-sharing/ScheduleBar.vue';
import { pairStatusLabels } from '@/components/job-sharing/types';
import type {
    PairStatus,
    ScheduleBarBlock,
    ScheduleBlock,
} from '@/components/job-sharing/types';
import { index as invitationsIndex } from '@/routes/candidate/invitations';
import { show as offerShow } from '@/routes/candidate/offers';

type Member = {
    id: number;
    first_name: string;
    display_name: string;
    headline: string | null;
    preferred_day_part_label: string | null;
    is_me: boolean;
    is_initiator: boolean;
    has_accepted: boolean;
    has_confirmed_schedule: boolean;
};

const props = defineProps<{
    pair: {
        id: number;
        status: PairStatus;
        submitted_at: string | null;
        has_saved_schedule: boolean;
    };
    offer: {
        id: number;
        title: string;
        company: string;
        city: string | null;
        work_mode_label: string;
        employment_fraction_label: string;
        is_published: boolean;
        workday_starts_at: string;
        workday_ends_at: string;
    };
    members: Member[];
    schedule: ScheduleBlock[];
    messages: {
        id: number;
        body: string;
        author_name: string;
        is_mine: boolean;
        created_at: string;
    }[];
    can: {
        respond: boolean;
        chat: boolean;
        send_message: boolean;
        plan_schedule: boolean;
        cancel: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Job sharing', href: PairController.index() }],
    },
});

usePoll(4000, { only: ['messages', 'members', 'pair', 'schedule', 'can'] });

const tones: ScheduleBarBlock['tone'][] = ['peach', 'yellow'];

const me = computed(() => props.members.find((member) => member.is_me));
const title = computed(
    () =>
        `Czat pary: ${props.members.map((member) => member.first_name).join(' i ')}`,
);

function memberName(memberId: number): string {
    return (
        props.members.find((member) => member.id === memberId)?.first_name ?? ''
    );
}

function toneOf(memberId: number): ScheduleBarBlock['tone'] {
    const index = props.members.findIndex((member) => member.id === memberId);

    return tones[Math.max(0, index)] ?? 'peach';
}

function messageTone(isMine: boolean): ScheduleBarBlock['tone'] {
    const author = props.members.find((member) => member.is_me === isMine);

    return author ? toneOf(author.id) : 'peach';
}

function cloneSchedule(): ScheduleBlock[] {
    return props.schedule.map((block) => ({
        ...block,
        starts_at: block.starts_at.slice(0, 5),
        ends_at: block.ends_at.slice(0, 5),
    }));
}

const form = useForm({ schedule: cloneSchedule() });

watch(
    () => JSON.stringify(props.schedule),
    () => {
        if (!form.isDirty) {
            form.defaults({ schedule: cloneSchedule() });
            form.reset();
        }
    },
);

const barBlocks = computed<ScheduleBarBlock[]>(() =>
    form.schedule.map((block) => ({
        key: block.candidate_profile_id,
        label: memberName(block.candidate_profile_id),
        starts_at: block.starts_at,
        ends_at: block.ends_at,
        tone: toneOf(block.candidate_profile_id),
    })),
);

const summary = computed(() =>
    [...form.schedule]
        .sort((first, second) =>
            first.starts_at.localeCompare(second.starts_at),
        )
        .map(
            (block) =>
                `${memberName(block.candidate_profile_id)} ${formatHour(block.starts_at)}–${formatHour(block.ends_at)}`,
        )
        .join(', '),
);

const scheduleError = ref<string | null>(null);
const isSending = ref(false);

const bothConfirmed = computed(
    () =>
        props.members.length === 2 &&
        props.members.every((member) => member.has_confirmed_schedule),
);

function saveSchedule(): void {
    scheduleError.value = null;
    form.put(PairScheduleController.update.url(props.pair.id), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: (errors) => (scheduleError.value = errors.schedule ?? null),
    });
}

function post(url: string): void {
    scheduleError.value = null;
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => (isSending.value = true),
            onFinish: () => (isSending.value = false),
            onError: (errors) =>
                (scheduleError.value = errors.schedule ?? null),
        },
    );
}

const thread = ref<HTMLElement | null>(null);

function scrollToBottom(): void {
    void nextTick(() => {
        thread.value?.scrollTo({ top: thread.value.scrollHeight });
    });
}

onMounted(scrollToBottom);
watch(() => props.messages.length, scrollToBottom);

function submitOnEnter(event: KeyboardEvent, submit: () => void): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}

const inputClass =
    'mt-1 h-10 w-full rounded-2xl border border-brand-line bg-white px-3 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40 disabled:opacity-60';
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-8">
        <Link
            :href="PairController.index()"
            class="inline-flex items-center gap-1 self-start text-sm font-semibold text-brand-green hover:underline"
        >
            <ArrowLeft class="size-4" /> Job sharing
        </Link>

        <header
            class="flex flex-col gap-3 rounded-3xl bg-white p-6 shadow-sm sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0">
                <h1
                    class="text-2xl font-extrabold tracking-tight text-brand-green md:text-3xl"
                >
                    {{ title }}
                </h1>
                <p class="mt-1 text-sm text-brand-green/80">
                    <Link
                        v-if="offer.is_published"
                        :href="offerShow(offer.id)"
                        class="font-semibold hover:underline"
                        >{{ offer.title }}</Link
                    >
                    <span v-else class="font-semibold">{{ offer.title }}</span>
                    · {{ offer.company }}
                    <template v-if="offer.city"> · {{ offer.city }}</template>
                    · {{ offer.employment_fraction_label }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="(member, index) in members"
                        :key="member.id"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium text-brand-green"
                        :class="
                            index === 0 ? 'bg-brand-peach' : 'bg-brand-yellow'
                        "
                    >
                        {{ member.display_name
                        }}<template v-if="member.is_me"> (Ty)</template>
                        <template v-if="member.preferred_day_part_label">
                            ·
                            {{
                                member.preferred_day_part_label.toLowerCase()
                            }}</template
                        >
                    </span>
                </div>
            </div>
            <Chip
                :tone="
                    pair.status === 'hired'
                        ? 'peach'
                        : pair.status === 'submitted'
                          ? 'dark'
                          : 'soft'
                "
                class="shrink-0 self-start"
            >
                {{ pairStatusLabels[pair.status] }}
            </Chip>
        </header>

        <section
            v-if="can.respond"
            class="flex flex-col gap-4 rounded-3xl bg-brand-mint-soft p-6 text-brand-green sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-lg font-bold">
                    {{ members.find((member) => !member.is_me)?.display_name }}
                    zaprasza Cię do pary
                </p>
                <p class="text-sm text-brand-green/80">
                    Po akceptacji otworzy się czat pary i ustalicie podział
                    dnia. Widzicie się nawzajem tylko z imienia i inicjału
                    nazwiska.
                </p>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-full border border-brand-green px-5 py-2 text-sm font-semibold hover:bg-white"
                    :disabled="isSending"
                    @click="post(PairController.decline.url(pair.id))"
                >
                    Odrzuć
                </button>
                <button
                    type="button"
                    class="rounded-full bg-brand-green px-5 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft"
                    :disabled="isSending"
                    data-test="accept-pair"
                    @click="post(PairController.accept.url(pair.id))"
                >
                    Dołączam do pary
                </button>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <section
                class="flex h-[32rem] flex-col gap-3 rounded-3xl bg-white p-4 shadow-sm md:h-[36rem]"
            >
                <div
                    ref="thread"
                    class="flex flex-1 flex-col gap-3 overflow-y-auto rounded-3xl bg-brand-cream/60 p-4"
                    role="log"
                    aria-live="polite"
                    aria-relevant="additions"
                    aria-label="Wiadomości"
                    tabindex="0"
                    data-test="pair-thread"
                >
                    <p
                        v-if="!can.chat"
                        class="m-auto max-w-sm text-center text-sm text-brand-green/80"
                    >
                        Czat pary otworzy się, gdy dołączysz do pary.
                    </p>
                    <p
                        v-else-if="messages.length === 0"
                        class="m-auto max-w-sm text-center text-sm text-brand-green/80"
                    >
                        Napiszcie, które godziny Wam pasują. Pracodawca nie
                        widzi tego czatu.
                    </p>
                    <ChatBubble
                        v-for="message in messages"
                        :key="message.id"
                        :mine="message.is_mine"
                        :tone="messageTone(message.is_mine)"
                        :speaker="message.is_mine ? 'Ty' : message.author_name"
                        :meta="`${message.author_name} · ${formatBubbleTime(message.created_at)}`"
                    >
                        {{ message.body }}
                    </ChatBubble>
                </div>

                <Form
                    v-if="can.send_message"
                    v-bind="PairMessageController.store.form(pair.id)"
                    reset-on-success
                    :options="{ preserveScroll: true }"
                    class="flex flex-col gap-2"
                    #default="{ errors, processing, submit }"
                >
                    <InputError id="pair-body-error" :message="errors.body" />
                    <div
                        class="flex items-end gap-2 rounded-3xl border border-brand-line bg-white p-2 has-[textarea:focus-visible]:ring-2 has-[textarea:focus-visible]:ring-brand-green"
                    >
                        <textarea
                            name="body"
                            aria-label="Treść wiadomości"
                            :aria-invalid="errors.body ? true : undefined"
                            aria-describedby="pair-body-error"
                            rows="1"
                            required
                            maxlength="2000"
                            placeholder="Napisz do partnerki…"
                            class="max-h-40 min-h-11 flex-1 resize-none rounded-2xl bg-transparent px-3 py-2.5 text-sm text-brand-green outline-none placeholder:text-brand-green/70"
                            @keydown="submitOnEnter($event, submit)"
                        />
                        <button
                            type="submit"
                            :disabled="processing"
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-green text-white transition hover:bg-brand-green-soft disabled:opacity-50"
                            aria-label="Wyślij"
                        >
                            <SendHorizontal class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                </Form>
            </section>

            <aside class="flex flex-col gap-4">
                <section class="rounded-3xl bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-brand-green">
                        Podział dnia
                    </h2>
                    <p class="mt-1 text-xs text-brand-green/80">
                        Dzień pracy:
                        {{ formatHour(offer.workday_starts_at) }}–{{
                            formatHour(offer.workday_ends_at)
                        }}. Każda z Was ma jeden blok, razem bez przerw.
                    </p>

                    <div v-if="form.schedule.length" class="mt-4">
                        <ScheduleBar
                            :workday-starts-at="offer.workday_starts_at"
                            :workday-ends-at="offer.workday_ends_at"
                            :blocks="barBlocks"
                        />
                        <p
                            class="mt-3 text-sm font-semibold text-brand-green"
                            data-test="schedule-summary"
                        >
                            Podział: {{ summary }}
                        </p>
                    </div>
                    <p v-else class="mt-4 text-sm text-brand-green/80">
                        Podział ustalicie, gdy partnerka dołączy do pary.
                    </p>

                    <form
                        v-if="can.plan_schedule"
                        class="mt-4 space-y-3"
                        @submit.prevent="saveSchedule"
                    >
                        <div
                            v-for="block in form.schedule"
                            :key="block.candidate_profile_id"
                            class="grid grid-cols-[1fr_1fr] gap-2"
                        >
                            <p
                                class="col-span-2 flex items-center gap-2 text-xs font-semibold text-brand-green"
                            >
                                <span
                                    class="size-3 rounded-full"
                                    :class="
                                        toneOf(block.candidate_profile_id) ===
                                        'peach'
                                            ? 'bg-brand-peach'
                                            : 'bg-brand-yellow'
                                    "
                                />
                                {{ memberName(block.candidate_profile_id) }}
                            </p>
                            <label class="text-[11px] text-brand-green/80">
                                Od
                                <input
                                    v-model="block.starts_at"
                                    type="time"
                                    step="1800"
                                    :min="offer.workday_starts_at"
                                    :max="offer.workday_ends_at"
                                    :class="inputClass"
                                    required
                                />
                            </label>
                            <label class="text-[11px] text-brand-green/80">
                                Do
                                <input
                                    v-model="block.ends_at"
                                    type="time"
                                    step="1800"
                                    :min="offer.workday_starts_at"
                                    :max="offer.workday_ends_at"
                                    :class="inputClass"
                                    required
                                />
                            </label>
                        </div>
                        <button
                            v-if="form.isDirty || !pair.has_saved_schedule"
                            type="submit"
                            class="w-full rounded-full border-2 border-brand-green px-5 py-2 text-sm font-semibold text-brand-green hover:bg-brand-mint-soft disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            Zapisz propozycję
                        </button>
                    </form>

                    <InputError
                        class="mt-3"
                        :message="scheduleError ?? undefined"
                    />

                    <ul
                        v-if="pair.has_saved_schedule"
                        class="mt-4 space-y-1.5 border-t border-brand-cream pt-4 text-sm text-brand-green"
                    >
                        <li
                            v-for="member in members"
                            :key="member.id"
                            class="flex items-center gap-2"
                        >
                            <Check
                                v-if="member.has_confirmed_schedule"
                                class="size-4 text-brand-mint"
                            />
                            <CircleDashed
                                v-else
                                class="size-4 text-brand-green/40"
                            />
                            {{ member.first_name }}
                            {{
                                member.has_confirmed_schedule
                                    ? 'akceptuje podział'
                                    : 'jeszcze nie zaakceptowała'
                            }}
                        </li>
                    </ul>

                    <div
                        v-if="can.plan_schedule && pair.has_saved_schedule"
                        class="mt-4 flex flex-col gap-2"
                    >
                        <button
                            v-if="me && !me.has_confirmed_schedule"
                            type="button"
                            class="rounded-full bg-brand-yellow px-5 py-2.5 text-sm font-semibold text-brand-green hover:bg-brand-yellow/80 disabled:opacity-50"
                            :disabled="isSending || form.isDirty"
                            data-test="confirm-schedule"
                            @click="
                                post(
                                    PairScheduleController.confirm.url(pair.id),
                                )
                            "
                        >
                            Akceptuję podział
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-brand-green px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:cursor-not-allowed disabled:bg-brand-green/40 disabled:hover:bg-brand-green/40"
                            :disabled="
                                !bothConfirmed || isSending || form.isDirty
                            "
                            :aria-describedby="
                                !bothConfirmed || form.isDirty
                                    ? 'submit-pair-hint'
                                    : undefined
                            "
                            data-test="submit-pair"
                            @click="
                                post(PairScheduleController.submit.url(pair.id))
                            "
                        >
                            Wyślij pracodawcy
                        </button>
                        <p
                            v-if="!bothConfirmed || form.isDirty"
                            id="submit-pair-hint"
                            class="text-center text-xs text-brand-green/80"
                            data-test="submit-pair-hint"
                        >
                            {{
                                form.isDirty
                                    ? 'Najpierw zapisz zmiany w podziale.'
                                    : 'Obie musicie zaakceptować podział, zanim wyślecie go pracodawcy.'
                            }}
                        </p>
                    </div>
                </section>

                <section
                    v-if="
                        pair.status === 'submitted' ||
                        pair.status === 'invited' ||
                        pair.status === 'accepted' ||
                        pair.status === 'hired' ||
                        pair.status === 'declined'
                    "
                    class="flex items-start gap-3 rounded-3xl bg-brand-green p-5 text-sm text-white"
                    data-test="pair-outcome"
                >
                    <ShieldCheck class="mt-0.5 size-5 shrink-0" />
                    <p v-if="pair.status === 'hired'">
                        Gratulacje! Firma potwierdziła zatrudnienie Waszej pary.
                        Szczegóły ustalicie z firmą w zakładce
                        <Link
                            :href="invitationsIndex()"
                            class="font-semibold underline underline-offset-2"
                            >„Zaproszenia”</Link
                        >.
                    </p>
                    <p v-else-if="pair.status === 'accepted'">
                        Obie przyjęłyście zaproszenie, więc firma zna już Wasze
                        dane i rozmawiacie na wspólnym czacie w zakładce
                        <Link
                            :href="invitationsIndex()"
                            class="font-semibold underline underline-offset-2"
                            >„Zaproszenia”</Link
                        >. Gdy firma potwierdzi zatrudnienie, dostaniecie
                        powiadomienie.
                    </p>
                    <p v-else-if="pair.status === 'declined'">
                        Jedna z Was odrzuciła zaproszenie pracodawcy, więc para
                        nie przejdzie dalej. Pozostałe zaproszenie zostało
                        wycofane.
                    </p>
                    <p v-else-if="pair.status === 'invited'">
                        Pracodawca zaprosił Waszą parę. Każda z Was odpowiada na
                        swoje zaproszenie w zakładce
                        <Link
                            :href="invitationsIndex()"
                            class="font-semibold underline underline-offset-2"
                            >„Zaproszenia”</Link
                        >.
                    </p>
                    <p v-else>
                        Pracodawca widzi Was jako parę: anonimowo, z
                        umiejętnościami i podziałem dnia. Dane kontaktowe
                        zobaczy dopiero po Waszej akceptacji zaproszenia.
                    </p>
                </section>

                <button
                    v-if="can.cancel"
                    type="button"
                    class="self-center text-xs text-brand-green/80 underline underline-offset-2 hover:text-brand-green"
                    :disabled="isSending"
                    @click="post(PairController.cancel.url(pair.id))"
                >
                    {{
                        pair.status === 'forming'
                            ? 'Wycofaj zaproszenie do pary'
                            : 'Rozwiąż parę'
                    }}
                </button>
            </aside>
        </div>
    </div>
</template>
