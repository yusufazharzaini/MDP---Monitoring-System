<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

export interface FormData {
    code: string;
    name: string;
    description: string | null;
    status: string;
}

defineProps<{
    form: InertiaForm<FormData>;
    options: { statuses: SelectOption[] };
}>();
</script>

<template>
    <TextInput id="code" v-model="form.code" label="Kode Departemen" required autofocus :error="form.errors.code" />
    <TextInput id="name" v-model="form.name" label="Nama Departemen" required :error="form.errors.name" />
    <SelectInput id="status" v-model="form.status" :label="t('common.status')" required :options="options.statuses" :error="form.errors.status" />
    <div class="sm:col-span-2">
        <TextareaInput id="description" v-model="form.description" :label="t('common.description')" :error="form.errors.description" />
    </div>
</template>
