<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps<{
    url: string;
    title: string;
    description: string;
    label?: string;
}>();

const isOpen = ref(false);
const isDeleting = ref(false);

function confirmDelete(): void {
    router.delete(props.url, {
        preserveScroll: true,
        onStart: () => (isDeleting.value = true),
        onFinish: () => (isDeleting.value = false),
        onSuccess: () => (isOpen.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full border border-red-200 px-4 py-1.5 text-sm font-semibold text-red-700 transition hover:bg-red-50"
                data-test="delete-button"
            >
                <Trash2 class="size-4" /> {{ label ?? 'Usuń' }}
            </button>
        </DialogTrigger>
        <DialogContent class="rounded-3xl">
            <DialogHeader>
                <DialogTitle class="text-brand-green">{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <button
                        type="button"
                        class="h-10 rounded-full border-2 border-brand-green px-5 text-sm font-semibold text-brand-green transition hover:bg-brand-mint-soft"
                    >
                        Anuluj
                    </button>
                </DialogClose>
                <button
                    type="button"
                    class="h-10 rounded-full bg-red-600 px-5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
                    :disabled="isDeleting"
                    data-test="confirm-delete-button"
                    @click="confirmDelete"
                >
                    Usuń na stałe
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
