<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import type { ProblemDeliveryContext, ProblemFormOptions } from '@/Types';

export interface FormData {
    problem_category_id: number | null;
    material_id: number | null;
    problem_date: string;
    severity: string;
    description: string;
    root_cause: string | null;
    pic: string | null;
    due_date: string | null;
}

defineProps<{
    form: InertiaForm<FormData>;
    delivery: ProblemDeliveryContext;
    options: ProblemFormOptions;
}>();
</script>

<template>
    <SelectInput
        id="problem_category_id"
        v-model="form.problem_category_id"
        label="Kategori Problem"
        required
        numeric
        placeholder="Pilih kategori"
        :options="options.categories"
        :error="form.errors.problem_category_id"
    />
    <SelectInput
        id="severity"
        v-model="form.severity"
        label="Severity"
        required
        :options="options.severities"
        :error="form.errors.severity"
        hint="Menentukan target penyelesaian bila tanggal target dikosongkan."
    />
    <SelectInput
        id="material_id"
        v-model="form.material_id"
        label="Material"
        numeric
        placeholder="Tidak spesifik ke satu material"
        :options="delivery.materials"
        :error="form.errors.material_id"
        hint="Hanya material yang benar-benar diterima pada delivery ini."
    />
    <TextInput
        id="pic"
        v-model="form.pic"
        label="PIC"
        :error="form.errors.pic"
        hint="Penanggung jawab tindak lanjut."
    />
    <TextInput
        id="problem_date"
        v-model="form.problem_date"
        type="date"
        label="Tanggal Problem"
        required
        :error="form.errors.problem_date"
    />
    <TextInput
        id="due_date"
        v-model="form.due_date"
        type="date"
        label="Target Penyelesaian"
        :error="form.errors.due_date"
        hint="Kosongkan untuk mengikuti standar severity."
    />
    <div class="sm:col-span-2">
        <TextareaInput
            id="description"
            v-model="form.description"
            label="Deskripsi Problem"
            required
            :error="form.errors.description"
        />
    </div>
    <div class="sm:col-span-2">
        <TextareaInput
            id="root_cause"
            v-model="form.root_cause"
            label="Root Cause"
            :error="form.errors.root_cause"
            hint="Dapat diisi kemudian setelah investigasi."
        />
    </div>
</template>
