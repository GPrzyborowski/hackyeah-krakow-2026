<script setup lang="ts">
const { tone = 'soft' } = defineProps<{
    tone?: 'soft' | 'dark' | 'yellow' | 'peach' | 'outline' | 'mint';
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium"
        :class="{
            'bg-brand-cream text-brand-green': tone === 'soft',
            'bg-brand-green text-white': tone === 'dark',
            'bg-brand-yellow text-brand-green': tone === 'yellow',
            'bg-brand-peach text-brand-green': tone === 'peach',
            'border border-brand-green/30 text-brand-green': tone === 'outline',
            'bg-brand-mint-soft text-brand-green': tone === 'mint',
        }"
    >
        <slot />
    </span>
</template>
