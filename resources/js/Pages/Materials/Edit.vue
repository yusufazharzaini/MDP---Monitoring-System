<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { SelectOption } from '@/Types';

const props = defineProps<{
    record: FormData & { id: number; ulid?: string };
    options: { statuses: SelectOption[]; categories: SelectOption[]; uoms: SelectOption[] };
}>();

const form = useForm<FormData>({ ...props.record } as FormData);

const backHref = route('materials.index');

function submit(): void {
    form.put(route('materials.update', props.record.ulid));
}
</script>

<template>
    <ResourceForm
        title="Ubah Material"
        :subtitle="`Memperbarui ${record.code} — ${record.name}`"
        current="materials"
        :back-href="backHref"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        submit-label="Simpan perubahan"
        @submit="submit"
    >
        <Fields :form="form" :options="options" />
    </ResourceForm>
</template>
