<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import ToggleInput from '@/Components/Form/ToggleInput.vue';
import type { SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

export interface FormData {
    code: string;
    name: string;
    category_id: number | null;
    uom_id: number | null;
    specification: string | null;
    minimum_stock: number;
    critical_stock: number;
    lead_time_days: number;
    is_critical: boolean;
    status: string;
}

defineProps<{
    form: InertiaForm<FormData>;
    options: { statuses: SelectOption[]; categories: SelectOption[]; uoms: SelectOption[] };
}>();
</script>

<template>
    <TextInput id="code" v-model="form.code" :label="t('material.code')" required autofocus :error="form.errors.code" />
    <TextInput id="name" v-model="form.name" :label="t('material.name')" required :error="form.errors.name" />
    <SelectInput id="category_id" v-model="form.category_id" :label="t('common.category')" required numeric :placeholder="t('select.category')" :options="options.categories" :error="form.errors.category_id" />
    <SelectInput id="uom_id" v-model="form.uom_id" :label="t('common.unit')" required numeric :placeholder="t('select.unit')" :options="options.uoms" :error="form.errors.uom_id" />
    <TextInput id="minimum_stock" v-model="form.minimum_stock" type="number" :min="0" step="0.0001" :label="t('material.minimum_stock')" required :error="form.errors.minimum_stock" />
    <TextInput id="critical_stock" v-model="form.critical_stock" type="number" :min="0" step="0.0001" :label="t('material.critical_stock')" required :error="form.errors.critical_stock" hint="Harus &lt;= minimum stock" />
    <TextInput id="lead_time_days" v-model="form.lead_time_days" type="number" :min="0" :label="t('po.lead_time_days')" required :error="form.errors.lead_time_days" />
    <SelectInput id="status" v-model="form.status" :label="t('common.status')" required :options="options.statuses" :error="form.errors.status" />
    <div class="sm:col-span-2">
        <TextareaInput id="specification" v-model="form.specification" :label="t('material.specification')" :error="form.errors.specification" />
    </div>
    <div class="sm:col-span-2">
        <ToggleInput id="is_critical" v-model="form.is_critical" :label="t('material.mark_critical')" hint="Material bertanda critical selalu dihitung pada KPI Critical Material dashboard." />
    </div>
</template>
