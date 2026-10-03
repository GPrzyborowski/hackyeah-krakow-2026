<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

const props = withDefaults(
    defineProps<{
        name: string;
        photoUrl?: string | null;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { photoUrl: null, size: 'md' },
);

const initial = computed(() => props.name.trim().charAt(0).toUpperCase());

const sizeClass = computed(
    () =>
        ({
            sm: 'size-9 text-sm',
            md: 'size-11 text-base',
            lg: 'size-20 text-2xl',
        })[props.size],
);
</script>

<template>
    <Avatar class="shrink-0 overflow-hidden rounded-full" :class="sizeClass">
        <AvatarImage
            v-if="photoUrl"
            :src="photoUrl"
            :alt="`Zdjęcie: ${name}`"
            class="object-cover"
        />
        <AvatarFallback
            class="rounded-full bg-brand-peach font-bold text-brand-green"
            aria-hidden="true"
        >
            {{ initial }}
        </AvatarFallback>
    </Avatar>
</template>
