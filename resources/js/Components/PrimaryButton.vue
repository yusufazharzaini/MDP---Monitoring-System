<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        disabled?: boolean;
        loading?: boolean;
    }>(),
    { type: 'button', disabled: false, loading: false },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:cursor-not-allowed disabled:opacity-60"
    >
        <span
            v-if="loading"
            class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
