<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Copy, Link2, Share2 } from '@lucide/vue';
import { useClipboard, useShare } from '@vueuse/core';
import { ref } from 'vue';
import JoinLinkController from '@/actions/App/Http/Controllers/JobSharing/JoinLinkController';
import { formatLongDate } from '@/components/employer/format';
import InputError from '@/components/InputError.vue';
import type { JoinLink } from '@/components/job-sharing/types';

const { offerId, offerTitle, joinLink } = defineProps<{
    offerId: number;
    offerTitle: string;
    joinLink: JoinLink | null;
}>();

const {
    copy,
    copied,
    isSupported: canCopy,
} = useClipboard({ copiedDuring: 2500, legacy: true });
const { share, isSupported: canShare } = useShare();

const isCreating = ref(false);
const error = ref<string | null>(null);

function createLink(): void {
    router.post(
        JoinLinkController.store.url(offerId),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isCreating.value = true;
                error.value = null;
            },
            onError: (errors) => (error.value = errors.join_link ?? null),
            onFinish: () => (isCreating.value = false),
        },
    );
}

function shareLink(link: JoinLink): void {
    void share({
        title: 'Aplikujmy razem w parze',
        text: `Zapraszam Cię do pary job-sharing w ofercie „${offerTitle}” w mumjobs.`,
        url: link.url,
    }).catch(() => undefined);
}
</script>

<template>
    <div class="rounded-3xl bg-white p-5" data-test="join-link-card">
        <div class="flex items-center gap-2 text-brand-green">
            <Link2 class="size-4" />
            <h3 class="font-semibold">Zaproś koleżankę linkiem</h3>
        </div>

        <template v-if="joinLink">
            <p class="mt-1 text-sm text-brand-green/80">
                Wyślij ten link osobie, z którą chcesz dzielić etat. Po
                założeniu konta lub zalogowaniu dołączy do Twojej pary.
            </p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <label class="sr-only" for="join-link-url">Link do pary</label>
                <input
                    id="join-link-url"
                    :value="joinLink.url"
                    readonly
                    class="h-10 min-w-0 flex-1 rounded-2xl border border-brand-line bg-brand-cream/60 px-3 text-sm text-brand-green outline-none focus:border-brand-green focus:ring-2 focus:ring-brand-green/40"
                    data-test="join-link-url"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <div class="flex gap-2">
                    <button
                        v-if="canCopy"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-4 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft"
                        data-test="copy-join-link"
                        @click="copy(joinLink.url)"
                    >
                        <Check v-if="copied" class="size-4" />
                        <Copy v-else class="size-4" />
                        {{ copied ? 'Skopiowano' : 'Kopiuj link' }}
                    </button>
                    <button
                        v-if="canShare"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-brand-green px-4 py-2 text-sm font-semibold text-brand-green hover:bg-brand-mint-soft"
                        @click="shareLink(joinLink)"
                    >
                        <Share2 class="size-4" /> Udostępnij
                    </button>
                </div>
            </div>
            <p class="mt-2 text-xs text-brand-green/80" aria-live="polite">
                Link ważny do {{ formatLongDate(joinLink.expires_at) }}. Działa
                dla jednej osoby.
            </p>
        </template>

        <template v-else>
            <p class="mt-1 text-sm text-brand-green/80">
                Znasz kogoś, z kim chcesz dzielić ten etat? Wygeneruj link i
                wyślij go jej – nie musi mieć jeszcze konta w mumjobs.
            </p>
            <InputError class="mt-2" :message="error ?? undefined" />
            <button
                type="button"
                class="mt-3 inline-flex items-center gap-1.5 rounded-full border-2 border-brand-green px-5 py-2 text-sm font-semibold text-brand-green hover:bg-brand-mint-soft disabled:opacity-50"
                :disabled="isCreating"
                data-test="create-join-link"
                @click="createLink"
            >
                <Link2 class="size-4" /> Zaproś koleżankę linkiem
            </button>
        </template>
    </div>
</template>
