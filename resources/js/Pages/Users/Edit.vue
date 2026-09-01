<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption, UserRecord } from '@/Types';

const props = defineProps<{
    record: UserRecord;
    options: {
        roles: SelectOption[];
        statuses: SelectOption[];
        departments: SelectOption[];
        plants: SelectOption[];
    };
    isSelf: boolean;
}>();

const form = useForm<FormData>({
    name: props.record.name,
    email: props.record.email,
    // Blank means "leave the existing password alone".
    password: '',
    password_confirmation: '',
    department_id: props.record.department_id,
    plant_id: props.record.plant_id,
    employee_code: props.record.employee_code ?? '',
    position: props.record.position ?? '',
    phone: props.record.phone ?? '',
    status: props.record.status,
    roles: [...props.record.roles],
} as FormData);

function submit(): void {
    form.put(route('users.update', props.record.ulid));
}
</script>

<template>
    <ResourceForm
        :title="`Ubah ${record.name}`"
        subtitle="Perbarui data akun dan perannya."
        current="users"
        :back-href="route('users.index')"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <div v-if="isSelf" class="sm:col-span-2 rounded-lg bg-warning-ground px-4 py-3 text-sm text-warning ring-1 ring-warning/30">
            Ini akun Anda sendiri. Menonaktifkannya atau menghapus seluruh perannya akan ditolak,
            karena efeknya baru terasa saat Anda mencoba masuk kembali.
        </div>

        <Fields :form="form" :options="options" is-edit :is-self="isSelf" />
    </ResourceForm>
</template>
