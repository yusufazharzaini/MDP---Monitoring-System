<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{ options: { statuses: SelectOption[] } }>();

const form = useForm<FormData>({
    code: '',
    name: '',
    address: '',
    city: '',
    pic_name: '',
    pic_phone: '',
    status: 'ACTIVE',
} as FormData);

const backHref = route('plants.index');

function submit(): void {
    form.post(route('plants.store'));
}
</script>

<template>
    <ResourceForm
        :title="t('master.plant_add')"
        subtitle="Lengkapi data berikut lalu simpan."
        current="plants"
        :back-href="backHref"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
