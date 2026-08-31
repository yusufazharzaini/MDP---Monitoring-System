<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';

defineProps<{ message?: string }>();
defineEmits<{ retry: [] }>();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center" role="alert">
        <span class="flex size-11 items-center justify-center rounded-full bg-critical/12 text-critical">
            <AppIcon name="critical" :size="22" />
        </span>
        <p class="text-sm font-semibold text-ink">Gagal memuat data</p>
        <p class="max-w-sm text-sm leading-relaxed text-ink-muted">
            {{ message || 'Terjadi kesalahan saat mengambil data dashboard.' }}
        </p>
        <button
            type="button"
            class="mt-1 inline-flex items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink transition hover:border-info hover:text-info"
            @click="$emit('retry')"
        >
            <AppIcon name="refresh" :size="14" />
            Coba lagi
        </button>
    </div>
</template>
