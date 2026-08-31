<script setup lang="ts">
withDefaults(defineProps<{ height?: string; width?: string; rounded?: string }>(), {
    height: '1rem',
    width: '100%',
    rounded: '0.375rem',
});
</script>

<template>
    <div
        class="animate-pulse bg-line-strong/50"
        :style="{ height, width, borderRadius: rounded }"
        aria-hidden="true"
    />
</template>
