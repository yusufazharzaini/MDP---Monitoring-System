<script setup lang="ts">
import FormField from '@/Components/Form/FormField.vue';
import type { SelectOption } from '@/Types';

withDefaults(
    defineProps<{
        id: string;
        label: string;
        options: SelectOption[];
        error?: string;
        hint?: string;
        required?: boolean;
        placeholder?: string;
        numeric?: boolean;
    }>(),
    { numeric: false },
);

const model = defineModel<string | number | null>();
</script>

<template>
    <FormField :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <select
            :id="id"
            class="field-input"
            :class="error ? 'border-critical' : ''"
            :aria-invalid="error ? 'true' : undefined"
            :value="model ?? ''"
            @change="
                model = (() => {
                    const raw = ($event.target as HTMLSelectElement).value;
                    return raw === '' ? null : numeric ? Number(raw) : raw;
                })()
            "
        >
            <option v-if="placeholder" value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </FormField>
</template>
