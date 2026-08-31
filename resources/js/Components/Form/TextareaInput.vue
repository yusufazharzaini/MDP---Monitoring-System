<script setup lang="ts">
import FormField from '@/Components/Form/FormField.vue';

withDefaults(
    defineProps<{ id: string; label: string; error?: string; hint?: string; rows?: number }>(),
    { rows: 3 },
);

const model = defineModel<string | null>();
</script>

<template>
    <FormField :id="id" :label="label" :error="error" :hint="hint">
        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :aria-invalid="error ? 'true' : undefined"
            class="field-input resize-y"
            :class="error ? 'border-critical' : ''"
        />
    </FormField>
</template>
