<script setup lang="ts">
import StatusBadge from '@/Components/StatusBadge.vue';
import type { SupplierPerformanceRow } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{ rows: SupplierPerformanceRow[]; target: number }>();

const number = new Intl.NumberFormat('id-ID');
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[34rem] text-sm">
            <thead>
                <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.rank') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.supplier') }}</th>
                    <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('common.total') }}</th>
                    <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('state.on_time') }}</th>
                    <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('metric.service_rate') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.supplier_id"
                    class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                >
                    <td class="px-5 py-3 text-ink-muted tabular-nums">{{ row.rank }}</td>
                    <td class="px-5 py-3">
                        <p class="font-medium text-ink">{{ row.supplier_name }}</p>
                        <p class="text-xs text-ink-subtle">{{ row.supplier_code }}</p>
                    </td>
                    <td class="px-5 py-3 text-right text-ink tabular-nums">
                        {{ number.format(row.total_delivery) }}
                    </td>
                    <td class="px-5 py-3 text-right text-ink tabular-nums">
                        {{ number.format(row.on_time_delivery) }}
                    </td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums"
                        :class="row.service_rate >= target ? 'text-ink' : 'text-critical'">
                        {{ row.service_rate.toFixed(1) }}%
                    </td>
                    <td class="px-5 py-3">
                        <StatusBadge :label="row.grade_label" :variant="row.grade_variant" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
