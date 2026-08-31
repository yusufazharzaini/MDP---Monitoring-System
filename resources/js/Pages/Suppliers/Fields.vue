<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { SelectOption } from '@/Types';

export interface FormData {
    code: string;
    name: string;
    short_name: string | null;
    address: string | null;
    city: string | null;
    country: string;
    pic_name: string | null;
    pic_email: string | null;
    pic_phone: string | null;
    lead_time_days: number;
    payment_term: string | null;
    supplier_type: string;
    status: string;
}

defineProps<{
    form: InertiaForm<FormData>;
    options: { types: SelectOption[]; statuses: SelectOption[] };
}>();
</script>

<template>
    <TextInput id="code" v-model="form.code" label="Kode Supplier" required autofocus :error="form.errors.code" />
    <TextInput id="name" v-model="form.name" label="Nama Supplier" required :error="form.errors.name" />
    <TextInput id="short_name" v-model="form.short_name" label="Nama Singkat" :error="form.errors.short_name" hint="Dipakai pada tabel dashboard" />
    <TextInput id="city" v-model="form.city" label="Kota" :error="form.errors.city" />
    <TextInput id="country" v-model="form.country" label="Negara" required :error="form.errors.country" />
    <TextInput id="pic_name" v-model="form.pic_name" label="Nama PIC" :error="form.errors.pic_name" />
    <TextInput id="pic_email" v-model="form.pic_email" type="email" label="Email PIC" :error="form.errors.pic_email" />
    <TextInput id="pic_phone" v-model="form.pic_phone" label="Telepon PIC" :error="form.errors.pic_phone" />
    <TextInput id="lead_time_days" v-model="form.lead_time_days" type="number" :min="0" label="Lead Time (hari)" required :error="form.errors.lead_time_days" />
    <TextInput id="payment_term" v-model="form.payment_term" label="Payment Term" :error="form.errors.payment_term" />
    <SelectInput id="supplier_type" v-model="form.supplier_type" label="Tipe Supplier" required :options="options.types" :error="form.errors.supplier_type" />
    <SelectInput id="status" v-model="form.status" label="Status" required :options="options.statuses" :error="form.errors.status" />
    <div class="sm:col-span-2">
        <TextareaInput id="address" v-model="form.address" label="Alamat" :error="form.errors.address" />
    </div>
</template>
