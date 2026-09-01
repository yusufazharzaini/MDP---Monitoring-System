<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';

defineProps<{
    options: {
        roles: SelectOption[];
        statuses: SelectOption[];
        departments: SelectOption[];
        plants: SelectOption[];
    };
}>();

const form = useForm<FormData>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    department_id: null,
    plant_id: null,
    employee_code: '',
    position: '',
    phone: '',
    status: 'ACTIVE',
    roles: [],
} as FormData);

function submit(): void {
    form.post(route('users.store'));
}
</script>

<template>
    <ResourceForm
        title="Tambah Pengguna"
        subtitle="Buat akun dan tentukan perannya."
        current="users"
        :back-href="route('users.index')"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
