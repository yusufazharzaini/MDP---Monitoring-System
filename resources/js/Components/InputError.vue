<script setup lang="ts">
defineProps<{ message?: string }>();
</script>

<template>
    <p v-if="message" class="mt-1.5 text-xs text-danger" role="alert">{{ message }}</p>
</template>
