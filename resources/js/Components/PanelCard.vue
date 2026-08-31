<script setup lang="ts">
import EmptyState from '@/Components/EmptyState.vue';
import ErrorState from '@/Components/ErrorState.vue';
import SkeletonBlock from '@/Components/SkeletonBlock.vue';

/**
 * The shared frame every dashboard panel sits in: title row, optional action
 * slot, and one place where loading / error / empty are decided so no panel
 * invents its own version of those states.
 */
defineProps<{
    title: string;
    subtitle?: string;
    loading?: boolean;
    error?: string | null;
    empty?: boolean;
    emptyMessage?: string;
}>();

defineEmits<{ retry: [] }>();
</script>

<template>
    <section class="card flex flex-col overflow-hidden">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ title }}</h2>
                <p v-if="subtitle" class="mt-0.5 text-xs text-ink-muted">{{ subtitle }}</p>
            </div>
            <slot name="action" />
        </header>

        <div class="relative flex-1">
            <ErrorState v-if="error" :message="error" @retry="$emit('retry')" />

            <div v-else-if="loading" class="p-5">
                <slot name="skeleton">
                    <div class="space-y-3">
                        <SkeletonBlock height="0.75rem" width="40%" />
                        <SkeletonBlock height="10rem" />
                    </div>
                </slot>
            </div>

            <EmptyState v-else-if="empty" :message="emptyMessage" />

            <slot v-else />
        </div>
    </section>
</template>
