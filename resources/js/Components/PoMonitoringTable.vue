<script setup lang="ts">
import StatusBadge from '@/Components/StatusBadge.vue';
import type { MonitoringRow } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{ rows: MonitoringRow[] }>();

const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
        .format(new Date(value));
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[58rem] text-sm">
            <thead>
                <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                    <th scope="col" class="px-5 py-3 text-left font-semibold">No</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('po.number_short') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.supplier') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.material') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('po.schedule') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('po.actual') }}</th>
                    <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('po.qty') }}</th>
                    <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('po.qty_receive') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                    <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('po.remark') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="`${row.po_number}-${row.material_code}`"
                    class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                >
                    <td class="px-5 py-3 text-ink-subtle tabular-nums">{{ row.no }}</td>
                    <td class="px-5 py-3 font-medium whitespace-nowrap text-ink">{{ row.po_number }}</td>
                    <td class="px-5 py-3 whitespace-nowrap text-ink-muted">{{ row.supplier }}</td>
                    <td class="px-5 py-3">
                        <p class="text-ink">{{ row.material }}</p>
                        <p class="text-xs text-ink-subtle">{{ row.material_code }}</p>
                    </td>
                    <td class="px-5 py-3 whitespace-nowrap text-ink-muted tabular-nums">
                        {{ formatDate(row.schedule_delivery_date) }}
                    </td>
                    <td
                        class="px-5 py-3 whitespace-nowrap tabular-nums"
                        :class="row.status_variant === 'danger' ? 'text-critical' : 'text-ink-muted'"
                    >
                        {{ formatDate(row.actual_delivery_date) }}
                    </td>
                    <td class="px-5 py-3 text-right text-ink tabular-nums">{{ number.format(row.qty_ordered) }}</td>
                    <td class="px-5 py-3 text-right text-ink tabular-nums">{{ number.format(row.qty_received) }}</td>
                    <td class="px-5 py-3">
                        <StatusBadge :label="row.status_label" :variant="row.status_variant" />
                    </td>
                    <td class="px-5 py-3 text-xs text-ink-muted">{{ row.remarks }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
