<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';

withDefaults(
    defineProps<{ title?: string; message?: string; icon?: string }>(),
    {
        title: 'Belum ada data',
        message: 'Tidak ada data untuk filter yang dipilih. Coba ubah periode atau lepaskan sebagian filter.',
        icon: 'box',
    },
);
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
        <span class="flex size-11 items-center justify-center rounded-full bg-ink-subtle/10 text-ink-subtle">
            <AppIcon :name="icon" :size="22" />
        </span>
        <p class="text-sm font-semibold text-ink">{{ title }}</p>
        <p class="max-w-sm text-sm leading-relaxed text-ink-muted">{{ message }}</p>
        <slot />
    </div>
</template>
