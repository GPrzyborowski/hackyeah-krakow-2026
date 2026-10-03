<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CompanyInvitationAcceptanceController from '@/actions/App/Http/Controllers/Employer/CompanyInvitationAcceptanceController';
import { formatLongDate } from '@/components/employer/format';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, login } from '@/routes';

type InvitationSummary = {
    token: string;
    email: string;
    company_name: string;
    invited_by: string | null;
    expires_at: string;
};

defineProps<{
    state: 'register' | 'accept' | 'login' | 'invalid';
    problem: string | null;
    invitation: InvitationSummary | null;
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Dołącz do zespołu',
        description: 'Zaproszenie do zespołu rekrutacyjnego w MomJobs.',
    },
});
</script>

<template>
    <Head title="Zaproszenie do zespołu" />

    <div class="flex flex-col gap-6">
        <div
            v-if="state === 'invalid' || invitation === null"
            class="grid gap-4 text-center"
        >
            <p class="rounded-2xl bg-brand-cream p-4 text-sm" role="alert">
                {{ problem }}
            </p>
            <div class="text-sm text-muted-foreground">
                <TextLink :href="login()" class="underline underline-offset-4"
                    >Zaloguj się</TextLink
                >
                ·
                <TextLink :href="home()" class="underline underline-offset-4"
                    >Strona główna</TextLink
                >
            </div>
        </div>

        <template v-else>
            <p class="rounded-2xl bg-brand-cream p-4 text-sm">
                <template v-if="invitation.invited_by"
                    >{{ invitation.invited_by }} zaprasza Cię</template
                >
                <template v-else>Zapraszamy Cię</template>
                do zespołu firmy
                <strong>{{ invitation.company_name }}</strong>
                (adres {{ invitation.email }}). Zaproszenie jest ważne do
                {{ formatLongDate(invitation.expires_at) }}.
            </p>

            <div v-if="state === 'login'" class="grid gap-4 text-center">
                <p class="text-sm">
                    Masz już konto z tym adresem e-mail. Zaloguj się, a wrócimy
                    na tę stronę, aby dołączyć do zespołu.
                </p>
                <Button as-child class="h-11 w-full rounded-full">
                    <TextLink :href="login()">Zaloguj się</TextLink>
                </Button>
            </div>

            <Form
                v-else-if="state === 'accept'"
                v-bind="
                    CompanyInvitationAcceptanceController.accept.form(
                        invitation.token,
                    )
                "
                v-slot="{ errors, processing }"
                class="grid gap-4"
            >
                <InputError :message="errors.invitation" />
                <Button
                    type="submit"
                    class="h-11 w-full rounded-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Dołącz do zespołu {{ invitation.company_name }}
                </Button>
            </Form>

            <Form
                v-else
                v-bind="
                    CompanyInvitationAcceptanceController.register.form(
                        invitation.token,
                    )
                "
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="grid gap-6"
            >
                <InputError :message="errors.invitation" />

                <div class="grid gap-2">
                    <Label for="invitation-email">E-mail</Label>
                    <Input
                        id="invitation-email"
                        type="email"
                        :model-value="invitation.email"
                        readonly
                        aria-readonly="true"
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
                    <InputError
                        id="password-error"
                        :message="errors.password"
                    />
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
                    class="h-11 w-full rounded-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Załóż konto i dołącz
                </Button>
            </Form>
        </template>
    </div>
</template>
