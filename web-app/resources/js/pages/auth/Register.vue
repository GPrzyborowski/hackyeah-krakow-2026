<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { privacy, terms } from '@/routes/public/legal';
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

const roleOptions = [
    { value: 'candidate', label: 'Szukam pracy' },
    { value: 'employer', label: 'Jestem pracodawcą' },
] as const;

function switchRoleWithKeyboard(event: KeyboardEvent): void {
    if (
        !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)
    ) {
        return;
    }

    event.preventDefault();
    role.value = role.value === 'candidate' ? 'employer' : 'candidate';

    const group = (event.currentTarget as HTMLElement).parentElement;
    void nextTick(() => {
        group
            ?.querySelector<HTMLElement>(`[data-test="role-${role.value}"]`)
            ?.focus();
    });
}

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
                <Label id="role-label">Kim jesteś?</Label>
                <input type="hidden" name="role" :value="role" />
                <div
                    class="grid grid-cols-2 gap-2"
                    role="radiogroup"
                    aria-labelledby="role-label"
                    aria-describedby="role-error"
                >
                    <button
                        v-for="option in roleOptions"
                        :key="option.value"
                        type="button"
                        role="radio"
                        :aria-checked="role === option.value"
                        :tabindex="role === option.value ? 0 : -1"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition"
                        :class="
                            role === option.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input hover:bg-accent'
                        "
                        :data-test="`role-${option.value}`"
                        @click="role = option.value"
                        @keydown="switchRoleWithKeyboard"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <InputError id="role-error" :message="errors.role" />
            </div>

            <div v-if="role === 'employer'" class="grid gap-2">
                <Label for="company_name">Nazwa firmy</Label>
                <Input
                    id="company_name"
                    :aria-invalid="errors.company_name ? true : undefined"
                    aria-describedby="company_name-error"
                    type="text"
                    required
                    name="company_name"
                    autocomplete="organization"
                    placeholder="np. Zielone Biuro"
                />
                <InputError
                    id="company_name-error"
                    :message="errors.company_name"
                />
            </div>

            <div v-if="role === 'employer'" class="grid gap-2">
                <Label for="company_nip">NIP firmy</Label>
                <Input
                    id="company_nip"
                    :aria-invalid="errors.company_nip ? true : undefined"
                    aria-describedby="company_nip-error"
                    type="text"
                    inputmode="numeric"
                    required
                    name="company_nip"
                    placeholder="np. 526-025-09-95"
                />
                <InputError
                    id="company_nip-error"
                    :message="errors.company_nip"
                />
            </div>

            <div class="grid gap-2">
                <Label for="name">Imię i nazwisko</Label>
                <Input
                    id="name"
                    :aria-invalid="errors.name ? true : undefined"
                    aria-describedby="name-error"
                    type="text"
                    required
                    v-focus
                    autocomplete="name"
                    name="name"
                    placeholder="Imię i nazwisko"
                />
                <InputError id="name-error" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    :aria-invalid="errors.email ? true : undefined"
                    aria-describedby="email-error"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="ty@example.com"
                />
                <InputError id="email-error" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Hasło</Label>
                <PasswordInput
                    id="password"
                    :aria-invalid="errors.password ? true : undefined"
                    aria-describedby="password-error"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Hasło"
                    :passwordrules="passwordRules"
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Powtórz hasło</Label>
                <PasswordInput
                    id="password_confirmation"
                    :aria-invalid="
                        errors.password_confirmation ? true : undefined
                    "
                    aria-describedby="password_confirmation-error"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Powtórz hasło"
                    :passwordrules="passwordRules"
                />
                <InputError
                    id="password_confirmation-error"
                    :message="errors.password_confirmation"
                />
            </div>

            <Button
                type="submit"
                class="mt-2 h-11 w-full rounded-full"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Załóż konto
            </Button>

            <p
                class="text-center text-xs text-muted-foreground"
                data-test="register-consent"
            >
                Zakładając konto akceptujesz
                <TextLink :href="terms()" target="_blank">Regulamin</TextLink>
                i
                <TextLink :href="privacy()" target="_blank"
                    >Politykę prywatności</TextLink
                >.
            </p>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Masz już konto?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Zaloguj się</TextLink
            >
        </div>
    </Form>
</template>
