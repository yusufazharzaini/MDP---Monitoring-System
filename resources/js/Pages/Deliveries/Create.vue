<script setup lang="ts">
import ReceiveForm from './ReceiveForm.vue';
import type { ReceivingContext, SelectOption } from '@/Types';

defineProps<{ purchaseOrder: ReceivingContext; options: { conditions: SelectOption[] } }>();
</script>

<template>
    <ReceiveForm :purchase-order="purchaseOrder" :options="options" />
</template>
