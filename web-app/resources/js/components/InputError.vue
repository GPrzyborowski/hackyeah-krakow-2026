<script setup lang="ts">
defineProps<{
    message?: string;
    id?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p :id="id" class="text-sm text-red-700 dark:text-red-400" role="alert">
            {{ message }}
        </p>
    </div>
</template>
