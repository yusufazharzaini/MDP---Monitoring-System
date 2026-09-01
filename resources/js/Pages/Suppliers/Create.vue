<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{ options: { types: SelectOption[]; statuses: SelectOption[] } }>();

const form = useForm<FormData>({
    code: '',
    name: '',
    short_name: '',
    address: '',
    city: '',
    country: 'Indonesia',
    pic_name: '',
    pic_email: '',
    pic_phone: '',
    lead_time_days: 7,
    supplier_type: 'LOCAL',
    payment_term: 'NET 30',
    status: 'ACTIVE',
} as FormData);

const backHref = route('suppliers.index');

function submit(): void {
    form.post(route('suppliers.store'));
}
</script>

<template>
    <ResourceForm
        :title="t('supplier.add_title')"
        subtitle="Lengkapi data berikut lalu simpan."
        current="suppliers"
        :back-href="backHref"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
