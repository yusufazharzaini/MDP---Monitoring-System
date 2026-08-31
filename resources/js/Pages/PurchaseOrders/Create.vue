<script setup lang="ts">
import Form from './Form.vue';
import type { PurchaseOrderFormOptions } from '@/Types';

defineProps<{ options: PurchaseOrderFormOptions }>();
</script>

<template>
    <Form :options="options" />
</template>
