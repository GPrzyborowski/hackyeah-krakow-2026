<script setup lang="ts">
/**
 * Renders HTML produced by the server's safe Markdown renderer (raw HTML stripped, unsafe links disabled).
 */
defineProps<{
    html: string;
}>();
</script>

<template>
    <div
        class="text-brand-green [&_a]:font-medium [&_a]:underline [&_a]:underline-offset-4 [&_blockquote]:my-5 [&_blockquote]:rounded-2xl [&_blockquote]:bg-brand-mint-soft [&_blockquote]:p-4 [&_blockquote_p]:my-0 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:font-semibold [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_p]:leading-relaxed [&_strong]:font-semibold [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6"
        data-test="markdown-preview"
        v-html="html"
    />
</template>
