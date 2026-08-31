<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';

defineProps<{ options: { statuses: SelectOption[] } }>();

const form = useForm<FormData>({
    code: '',
    name: '',
    description: '',
    status: 'ACTIVE',
} as FormData);

const backHref = route('departments.index');

function submit(): void {
    form.post(route('departments.store'));
}
</script>

<template>
    <ResourceForm
        title="Tambah Department"
        subtitle="Lengkapi data berikut lalu simpan."
        current="departments"
        :back-href="backHref"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
