<script setup lang="ts">
import Form from './Form.vue';
import type { PurchaseOrderFormOptions, PurchaseOrderRecord } from '@/Types';

defineProps<{ record: PurchaseOrderRecord; options: PurchaseOrderFormOptions }>();
</script>

<template>
    <Form :record="record" :options="options" />
</template>
