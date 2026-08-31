<script setup lang="ts">
import InputError from '@/Components/InputError.vue';

defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div>
        <label :for="id" class="field-label">
            {{ label }}
            <span v-if="required" class="text-critical" aria-hidden="true">*</span>
        </label>
        <slot />
        <p v-if="hint && !error" class="mt-1 text-xs text-ink-subtle">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>
