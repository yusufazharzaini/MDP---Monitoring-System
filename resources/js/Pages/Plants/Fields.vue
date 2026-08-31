<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { SelectOption } from '@/Types';

export interface FormData {
    code: string;
    name: string;
    address: string | null;
    city: string | null;
    pic_name: string | null;
    pic_phone: string | null;
    status: string;
}

defineProps<{
    form: InertiaForm<FormData>;
    options: { statuses: SelectOption[] };
}>();
</script>

<template>
    <TextInput id="code" v-model="form.code" label="Kode Plant" required autofocus :error="form.errors.code" />
    <TextInput id="name" v-model="form.name" label="Nama Plant" required :error="form.errors.name" />
    <TextInput id="city" v-model="form.city" label="Kota" :error="form.errors.city" />
    <TextInput id="pic_name" v-model="form.pic_name" label="Nama PIC" :error="form.errors.pic_name" />
    <TextInput id="pic_phone" v-model="form.pic_phone" label="Telepon PIC" :error="form.errors.pic_phone" />
    <SelectInput id="status" v-model="form.status" label="Status" required :options="options.statuses" :error="form.errors.status" />
    <div class="sm:col-span-2">
        <TextareaInput id="address" v-model="form.address" label="Alamat" :error="form.errors.address" />
    </div>
</template>
