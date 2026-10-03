<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Potwierdź adres e-mail',
        description: 'Kliknij link, który wysłaliśmy na Twój adres e-mail.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Weryfikacja e-maila" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-brand-green"
    >
        Wysłaliśmy nowy link weryfikacyjny na adres e-mail podany przy
        rejestracji.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button
            :disabled="processing"
            variant="secondary"
            class="h-11 rounded-full px-6"
        >
            <Spinner v-if="processing" />
            Wyślij link ponownie
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Wyloguj się
        </TextLink>
    </Form>
</template>
