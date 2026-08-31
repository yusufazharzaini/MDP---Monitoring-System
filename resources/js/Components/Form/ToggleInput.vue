<script setup lang="ts">
defineProps<{ id: string; label: string; hint?: string }>();

const model = defineModel<boolean>({ default: false });
</script>

<template>
    <div class="flex items-start gap-3 rounded-lg border border-line bg-canvas/40 p-3">
        <input
            :id="id"
            v-model="model"
            type="checkbox"
            class="mt-0.5 size-4 shrink-0 rounded border-line bg-canvas text-brand"
        />
        <label :for="id" class="cursor-pointer">
            <span class="block text-sm font-medium text-ink">{{ label }}</span>
            <span v-if="hint" class="mt-0.5 block text-xs text-ink-muted">{{ hint }}</span>
        </label>
    </div>
</template>
