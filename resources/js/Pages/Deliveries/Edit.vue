<script setup lang="ts">
import ReceiveForm from './ReceiveForm.vue';
import type { DeliveryRecord, ReceivingContext, SelectOption } from '@/Types';

defineProps<{
    record: DeliveryRecord;
    purchaseOrder: ReceivingContext;
    options: { conditions: SelectOption[] };
}>();
</script>

<template>
    <ReceiveForm :record="record" :purchase-order="purchaseOrder" :options="options" />
</template>
