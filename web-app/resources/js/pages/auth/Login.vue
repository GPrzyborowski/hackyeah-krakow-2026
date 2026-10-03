<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Witaj z powrotem',
        description: 'Zaloguj się, aby zobaczyć zaproszenia i oferty',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Logowanie" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-brand-green"
    >
        {{ status }}
    </div>

    <PasskeyVerify
        label="Zaloguj się kluczem dostępu"
        loading-label="Logowanie..."
        separator="Albo zaloguj się e-mailem"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    :aria-invalid="errors.email ? true : undefined"
                    aria-describedby="email-error"
                    type="email"
                    name="email"
                    required
                    v-focus
                    autocomplete="email"
                    placeholder="ty@example.com"
                />
                <InputError id="email-error" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Hasło</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                    >
                        Nie pamiętasz hasła?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    :aria-invalid="errors.password ? true : undefined"
                    aria-describedby="password-error"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Hasło"
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Zapamiętaj mnie</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 h-11 w-full rounded-full"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Zaloguj się
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Nie masz jeszcze konta?
            <TextLink :href="register()" :tabindex="5">Załóż profil</TextLink>
        </div>
    </Form>
</template>
