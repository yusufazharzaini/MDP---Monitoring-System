<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { SelectOption } from '@/Types';

export interface FormData {
    code: string;
    name: string;
    description: string | null;
    status: string;
}

defineProps<{
    form: InertiaForm<FormData>;
    options: { statuses: SelectOption[] };
}>();
</script>

<template>
    <TextInput id="code" v-model="form.code" label="Kode Kategori" required autofocus :error="form.errors.code" />
    <TextInput id="name" v-model="form.name" label="Nama Kategori" required :error="form.errors.name" />
    <SelectInput id="status" v-model="form.status" label="Status" required :options="options.statuses" :error="form.errors.status" />
    <div class="sm:col-span-2">
        <TextareaInput id="description" v-model="form.description" label="Deskripsi" :error="form.errors.description" />
    </div>
</template>
