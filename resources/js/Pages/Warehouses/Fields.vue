<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

export interface FormData {
    plant_id: number | null;
    code: string;
    name: string;
    address: string | null;
    status: string;
}

defineProps<{
    form: InertiaForm<FormData>;
    options: { statuses: SelectOption[]; plants: SelectOption[] };
}>();
</script>

<template>
    <SelectInput id="plant_id" v-model="form.plant_id" :label="t('entity.plant')" required numeric :placeholder="t('select.plant')" :options="options.plants" :error="form.errors.plant_id" />
    <TextInput id="code" v-model="form.code" :label="t('master.warehouse_code')" required :error="form.errors.code" hint="Unik dalam satu plant" />
    <TextInput id="name" v-model="form.name" :label="t('master.warehouse_name')" required :error="form.errors.name" />
    <SelectInput id="status" v-model="form.status" :label="t('common.status')" required :options="options.statuses" :error="form.errors.status" />
    <div class="sm:col-span-2">
        <TextareaInput id="address" v-model="form.address" :label="t('common.address')" :error="form.errors.address" />
    </div>
</template>
