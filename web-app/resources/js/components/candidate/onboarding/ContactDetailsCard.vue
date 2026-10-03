<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Lock, Phone, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import CandidateAvatar from '@/components/candidate/CandidateAvatar.vue';
import InputError from '@/components/InputError.vue';
import { privacy } from '@/routes/candidate/onboarding';
import { destroy, store } from '@/routes/candidate/onboarding/photo';

const props = defineProps<{
    name: string;
    phone: string | null;
    photoUrl: string | null;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const removing = ref(false);

const photoForm = useForm<{ photo: File | null }>({ photo: null });
const phoneForm = useForm<{ phone: string }>({ phone: props.phone ?? '' });

watch(
    () => props.phone,
    (value) => {
        phoneForm.phone = value ?? '';
        phoneForm.defaults();
    },
);

function uploadPhoto(event: Event) {
    const target = event.target as HTMLInputElement;
    photoForm.photo = target.files?.[0] ?? null;

    if (!photoForm.photo) {
        return;
    }

    photoForm.post(store.url(), {
        preserveScroll: true,
        onFinish: () => {
            photoForm.reset();

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function removePhoto() {
    router.delete(destroy.url(), {
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => (removing.value = false),
    });
}

function savePhone() {
    phoneForm.patch(privacy.url(), { preserveScroll: true });
}
</script>

<template>
    <section
        class="rounded-3xl bg-white p-6 shadow-sm"
        aria-labelledby="contact-details-heading"
    >
        <h2
            id="contact-details-heading"
            class="text-lg font-bold text-brand-green"
        >
            Zdjęcie i telefon
        </h2>
        <p class="mt-1 flex items-start gap-2 text-sm text-brand-green/80">
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Zdjęcie i telefon zobaczy tylko firma, której zaproszenie
            przyjmiesz.
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-4">
            <CandidateAvatar :name="name" :photo-url="photoUrl" size="lg" />
            <div class="flex flex-wrap gap-2">
                <label
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-brand-green px-4 py-2 text-sm font-semibold text-brand-green focus-within:ring-2 focus-within:ring-brand-green hover:bg-brand-cream"
                    :class="{
                        'pointer-events-none opacity-50': photoForm.processing,
                    }"
                >
                    <ImagePlus class="size-4" aria-hidden="true" />
                    {{ photoUrl ? 'Zmień zdjęcie' : 'Dodaj zdjęcie' }}
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        :disabled="photoForm.processing"
                        aria-describedby="photo-hint"
                        @change="uploadPhoto"
                    />
                </label>
                <button
                    v-if="photoUrl"
                    type="button"
                    :disabled="removing"
                    class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-brand-green hover:bg-brand-cream disabled:opacity-50"
                    @click="removePhoto"
                >
                    <Trash2 class="size-4" aria-hidden="true" /> Usuń zdjęcie
                </button>
            </div>
        </div>
        <p id="photo-hint" class="mt-2 text-xs text-brand-green/80">
            JPG, PNG lub WebP, do 3 MB.
        </p>
        <InputError class="mt-1" :message="photoForm.errors.photo" />

        <form class="mt-5" @submit.prevent="savePhone">
            <label
                for="candidate-phone"
                class="text-sm font-semibold text-brand-green"
                >Telefon</label
            >
            <div class="mt-1 flex flex-wrap gap-2">
                <div class="relative min-w-0 flex-1">
                    <Phone
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-brand-green/60"
                        aria-hidden="true"
                    />
                    <input
                        id="candidate-phone"
                        v-model="phoneForm.phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        placeholder="+48 600 100 200"
                        maxlength="20"
                        class="w-full rounded-full border border-brand-mint-soft bg-white py-2 pr-3 pl-9 text-sm text-brand-green"
                        :aria-invalid="
                            phoneForm.errors.phone ? true : undefined
                        "
                        aria-describedby="candidate-phone-error"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="phoneForm.processing || !phoneForm.isDirty"
                    class="rounded-full bg-brand-green px-5 py-2 text-sm font-semibold text-white hover:bg-brand-green-soft disabled:opacity-50"
                >
                    Zapisz
                </button>
            </div>
            <InputError
                id="candidate-phone-error"
                class="mt-1"
                :message="phoneForm.errors.phone"
            />
        </form>
    </section>
</template>
