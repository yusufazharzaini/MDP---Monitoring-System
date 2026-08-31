<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { SelectOption } from '@/Types';

export interface FormData {
    code: string;
    name: string;
    type: string;
    status: string;
}

defineProps<{
    form: InertiaForm<FormData>;
    options: { statuses: SelectOption[]; types: SelectOption[] };
}>();
</script>

<template>
    <TextInput id="code" v-model="form.code" label="Kode Satuan" required autofocus :error="form.errors.code" />
    <TextInput id="name" v-model="form.name" label="Nama Satuan" required :error="form.errors.name" />
    <SelectInput id="type" v-model="form.type" label="Tipe" required :options="options.types" :error="form.errors.type" />
    <SelectInput id="status" v-model="form.status" label="Status" required :options="options.statuses" :error="form.errors.status" />
</template>
