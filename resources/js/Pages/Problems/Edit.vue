<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { ProblemDeliveryContext, ProblemFormOptions, ProblemFormRecord } from '@/Types';

const props = defineProps<{
    record: ProblemFormRecord;
    delivery: ProblemDeliveryContext;
    options: ProblemFormOptions;
}>();

const form = useForm<FormData>({
    problem_category_id: props.record.problem_category_id,
    material_id: props.record.material_id,
    problem_date: props.record.problem_date ?? '',
    severity: props.record.severity,
    description: props.record.description,
    root_cause: props.record.root_cause ?? '',
    pic: props.record.pic ?? '',
    due_date: props.record.due_date ?? '',
} as FormData);

function submit(): void {
    form
        .transform((data) => ({
            ...data,
            due_date: data.due_date || null,
            material_id: data.material_id || null,
        }))
        .put(route('problems.update', props.record.ulid));
}
</script>

<template>
    <ResourceForm
        :title="`Ubah ${record.problem_number}`"
        subtitle="Perbarui detail problem lalu simpan."
        current="problems"
        :back-href="route('problems.show', record.ulid)"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :delivery="delivery" :options="options" />
    </ResourceForm>
</template>
