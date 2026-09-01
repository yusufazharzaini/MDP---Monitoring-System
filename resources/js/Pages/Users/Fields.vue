<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import FormField from '@/Components/Form/FormField.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import type { SelectOption } from '@/Types';

export interface FormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    department_id: number | null;
    plant_id: number | null;
    employee_code: string | null;
    position: string | null;
    phone: string | null;
    status: string;
    roles: string[];
}

defineProps<{
    form: InertiaForm<FormData>;
    options: {
        roles: SelectOption[];
        statuses: SelectOption[];
        departments: SelectOption[];
        plants: SelectOption[];
    };
    /** On edit, a blank password means "leave it alone". */
    isEdit?: boolean;
    /** Editing your own account: the fields that could lock you out are held. */
    isSelf?: boolean;
}>();
</script>

<template>
    <TextInput id="name" v-model="form.name" label="Nama" required autofocus :error="form.errors.name" />
    <TextInput id="email" v-model="form.email" type="email" label="Email" required :error="form.errors.email" />
    <TextInput
        id="password"
        v-model="form.password"
        type="password"
        label="Kata Sandi"
        :required="!isEdit"
        :error="form.errors.password"
        :hint="isEdit ? 'Kosongkan bila tidak ingin mengubah kata sandi.' : 'Minimal 8 karakter, mengandung huruf dan angka.'"
    />
    <TextInput
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        label="Konfirmasi Kata Sandi"
        :required="!isEdit"
    />
    <TextInput id="employee_code" v-model="form.employee_code" label="Nomor Induk" :error="form.errors.employee_code" />
    <TextInput id="position" v-model="form.position" label="Jabatan" :error="form.errors.position" />
    <SelectInput
        id="department_id"
        v-model="form.department_id"
        label="Departemen"
        numeric
        placeholder="Tidak ditentukan"
        :options="options.departments"
        :error="form.errors.department_id"
    />
    <SelectInput
        id="plant_id"
        v-model="form.plant_id"
        label="Plant"
        numeric
        placeholder="Tidak ditentukan"
        :options="options.plants"
        :error="form.errors.plant_id"
    />
    <TextInput id="phone" v-model="form.phone" label="Telepon" :error="form.errors.phone" />
    <SelectInput
        id="status"
        v-model="form.status"
        label="Status"
        required
        :options="options.statuses"
        :error="form.errors.status"
        :hint="isSelf ? 'Anda tidak dapat menonaktifkan akun Anda sendiri.' : undefined"
    />

    <div class="sm:col-span-2">
        <FormField id="roles" label="Peran" required :error="form.errors.roles">
            <div class="mt-1 grid gap-2 sm:grid-cols-3">
                <label
                    v-for="role in options.roles"
                    :key="role.value"
                    class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm transition"
                    :class="form.roles.includes(String(role.value)) ? 'bg-brand/10 text-ink ring-1 ring-brand/40' : 'text-ink-muted hover:bg-surface-hover'"
                >
                    <input
                        v-model="form.roles"
                        type="checkbox"
                        class="size-4 rounded border-line"
                        :value="String(role.value)"
                    />
                    {{ role.label }}
                </label>
            </div>
        </FormField>
    </div>
</template>
