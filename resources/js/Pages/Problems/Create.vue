<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ResourceForm from '@/Components/MasterData/ResourceForm.vue';
import Fields, { type FormData } from './Fields.vue';
import type { ProblemDeliveryContext, ProblemFormOptions } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    delivery: ProblemDeliveryContext;
    options: ProblemFormOptions;
}>();

const form = useForm<FormData>({
    problem_category_id: null,
    material_id: null,
    // A problem is observed on the day it is noticed, never later than today.
    problem_date: new Date().toISOString().slice(0, 10),
    severity: 'MEDIUM',
    description: '',
    root_cause: '',
    pic: '',
    due_date: '',
} as FormData);

function submit(): void {
    form
        .transform((data) => ({
            ...data,
            // An empty date input posts '', which is not a date; the service
            // treats a missing due date as "use the severity standard".
            due_date: data.due_date || null,
            material_id: data.material_id || null,
        }))
        .post(route('problems.store', props.delivery.ulid));
}
</script>

<template>
    <ResourceForm
        :title="t('problem.report_title')"
        :subtitle="`Delivery ${delivery.delivery_number} · ${delivery.supplier_name ?? ''}`"
        current="problems"
        :back-href="route('deliveries.show', delivery.ulid)"
        :processing="form.processing"
        :has-errors="Object.keys(form.errors).length > 0"
        @submit="submit"
    >
        <Fields :form="form" :delivery="delivery" :options="options" />
    </ResourceForm>
</template>
