<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

const role = ref<'candidate' | 'employer'>(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('role') === 'employer'
        ? 'employer'
        : 'candidate',
);

defineOptions({
    layout: {
        title: 'Załóż konto',
        description: 'Profil tworzysz raz. Firmy znajdą Cię same.',
    },
});
</script>

<template>
    <Head title="Rejestracja" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label>Kim jesteś?</Label>
                <input type="hidden" name="role" :value="role" />
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="option in [
                            { value: 'candidate', label: 'Szukam pracy' },
                            { value: 'employer', label: 'Jestem pracodawcą' },
                        ] as const"
                        :key="option.value"
                        type="button"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition"
                        :class="
                            role === option.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input hover:bg-accent'
                        "
                        :data-test="`role-${option.value}`"
                        @click="role = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <InputError :message="errors.role" />
            </div>

            <div v-if="role === 'employer'" class="grid gap-2">
                <Label for="company_name">Nazwa firmy</Label>
                <Input
                    id="company_name"
                    type="text"
                    required
                    name="company_name"
                    placeholder="np. Zielone Biuro"
                />
                <InputError :message="errors.company_name" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Imię i nazwisko</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Imię i nazwisko"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="ty@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Hasło</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Hasło"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Powtórz hasło</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Powtórz hasło"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 h-11 w-full rounded-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Załóż konto
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Masz już konto?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Zaloguj się</TextLink
            >
        </div>
    </Form>
</template>
