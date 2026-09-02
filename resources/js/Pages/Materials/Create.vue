<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{ options: { statuses: SelectOption[]; categories: SelectOption[]; uoms: SelectOption[] } }>();

const form = useForm<FormData>({
    code: '',
    name: '',
    category_id: null as number | null,
    uom_id: null as number | null,
    specification: '',
    minimum_stock: 0,
    critical_stock: 0,
    lead_time_days: 7,
    is_critical: false,
    status: 'ACTIVE',
} as FormData);

const backHref = route('materials.index');

function submit(): void {
    form.post(route('materials.store'));
}
</script>

<template>
    <ResourceForm
        :title="t('material.add_title')"
        subtitle="Lengkapi data berikut lalu simpan."
        current="materials"
        :back-href="backHref"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
