<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { store } from '@/routes/newsletter';
</script>

<template>
    <section
        class="grid gap-6 rounded-3xl bg-brand-green p-8 text-white md:grid-cols-2 md:items-center md:p-10"
        data-test="newsletter-signup"
    >
        <div>
            <h2 class="text-2xl leading-tight font-semibold sm:text-3xl">
                Jeden nowy tekst z bloga w tygodniu
            </h2>
            <p class="mt-2 text-sm text-white/80">
                Bez reklam. Wypiszesz się jednym kliknięciem.
            </p>
        </div>

        <Form
            v-bind="store.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            class="flex flex-col gap-2"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="flex flex-col gap-2 sm:flex-row">
                <label for="newsletter-email" class="sr-only">
                    Adres e-mail
                </label>
                <input
                    id="newsletter-email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="Twój e-mail"
                    class="h-11 min-w-0 flex-1 rounded-full border-0 bg-white px-5 text-sm text-brand-green placeholder:text-brand-green/70 focus-visible:ring-2 focus-visible:ring-brand-yellow focus-visible:outline-none"
                />
                <button
                    type="submit"
                    :disabled="processing"
                    class="h-11 shrink-0 rounded-full bg-brand-yellow px-6 text-sm font-semibold text-brand-green transition hover:bg-brand-peach disabled:opacity-60"
                >
                    Zapisz mnie
                </button>
            </div>
            <p v-if="errors.email" class="px-2 text-sm text-brand-peach">
                {{ errors.email }}
            </p>
            <p
                v-else-if="recentlySuccessful"
                class="px-2 text-sm text-brand-mint-soft"
                role="status"
            >
                Sprawdź skrzynkę i kliknij link, żeby potwierdzić zapis.
            </p>
        </Form>
    </section>
</template>
