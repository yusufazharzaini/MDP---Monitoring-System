<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    record: FormData & { id: number; ulid?: string };
    options: { statuses: SelectOption[] };
}>();

const form = useForm<FormData>({ ...props.record } as FormData);

const backHref = route('plants.index');

function submit(): void {
    form.put(route('plants.update', props.record.ulid));
}
</script>

<template>
    <ResourceForm
        :title="t('master.plant_edit')"
        :subtitle="`Memperbarui ${record.code} — ${record.name}`"
        current="plants"
        :back-href="backHref"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        submit-label="Simpan perubahan"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
