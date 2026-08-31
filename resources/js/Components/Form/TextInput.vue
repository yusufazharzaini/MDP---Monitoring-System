<script setup lang="ts">
import FormField from '@/Components/Form/FormField.vue';

withDefaults(
    defineProps<{
        id: string;
        label: string;
        type?: string;
        error?: string;
        hint?: string;
        required?: boolean;
        placeholder?: string;
        min?: number | string;
        step?: number | string;
        autofocus?: boolean;
    }>(),
    { type: 'text' },
);

const model = defineModel<string | number | null>();
</script>

<template>
    <FormField :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <input
            :id="id"
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :min="min"
            :step="step"
            :autofocus="autofocus"
            :aria-invalid="error ? 'true' : undefined"
            class="field-input"
            :class="error ? 'border-critical' : ''"
        />
    </FormField>
</template>
