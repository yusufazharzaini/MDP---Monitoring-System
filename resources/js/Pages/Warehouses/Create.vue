<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{ options: { statuses: SelectOption[]; plants: SelectOption[] } }>();

const form = useForm<FormData>({
    plant_id: null as number | null,
    code: '',
    name: '',
    address: '',
    status: 'ACTIVE',
} as FormData);

const backHref = route('warehouses.index');

function submit(): void {
    form.post(route('warehouses.store'));
}
</script>

<template>
    <ResourceForm
        :title="t('master.warehouse_add')"
        subtitle="Lengkapi data berikut lalu simpan."
        current="warehouses"
        :back-href="backHref"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
