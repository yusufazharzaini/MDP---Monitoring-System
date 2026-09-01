<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import type { DeliveryRecord } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    record: DeliveryRecord;
    can: { update: boolean; cancel: boolean; reportProblem: boolean };
}>();

const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });
const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const cancelling = ref(false);
const cancelForm = useForm({ reason: '' });

function cancel(): void {
    cancelForm.post(route('deliveries.cancel', props.record.ulid), {
        preserveScroll: true,
        onSuccess: () => {
            cancelling.value = false;
            cancelForm.reset();
        },
    });
}

const details = computed(() => [
    { label: 'Tanggal terima', value: dateFmt.format(new Date(props.record.delivery_date)) },
    { label: 'Purchase order', value: props.record.po_number ?? '—' },
    { label: 'Supplier', value: props.record.supplier_name ?? '—' },
    { label: 'Plant', value: props.record.plant_name ?? '—' },
    { label: 'No. surat jalan', value: props.record.do_number || '—' },
    { label: 'Kendaraan', value: props.record.vehicle_number || '—' },
    { label: 'Driver', value: props.record.driver_name || '—' },
    { label: 'Diterima oleh', value: props.record.received_by_name ?? '—' },
]);

const isCancelled = computed(() => props.record.status === 'CANCELLED');
</script>

<template>
    <Head :title="record.delivery_number" />

    <AppLayout
        current="deliveries"
        :title="record.delivery_number"
        :subtitle="`${record.supplier_name ?? ''} · ${record.plant_name ?? ''}`"
    >
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('deliveries.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >
                    &larr; Kembali ke daftar delivery
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="record.purchase_order_ulid"
                        :href="route('purchase-orders.show', record.purchase_order_ulid)"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                    >{{ t('delivery.view_po') }}</Link>
                    <Link
                        v-if="can.reportProblem"
                        :href="route('problems.create', record.ulid)"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-warning hover:text-warning"
                    >{{ t('problem.report') }}</Link>
                    <Link
                        v-if="can.update"
                        :href="route('deliveries.edit', record.ulid)"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                    >{{ t('delivery.correct') }}</Link>
                    <button
                        v-if="can.cancel"
                        type="button"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-critical hover:text-critical"
                        @click="cancelling = true"
                    >{{ t('common.cancel_record') }}</button>
                </div>
            </div>

            <div
                v-if="isCancelled"
                class="rounded-lg bg-critical-ground px-4 py-3 text-sm text-critical ring-1 ring-critical/30"
                role="status"
            >
                Delivery ini dibatalkan. Barisnya tetap tercatat, tetapi tidak lagi dihitung pada KPI
                maupun pemenuhan purchase order.
            </div>

            <section class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('delivery.info') }}</h2>
                    <StatusBadge :label="record.status_label" :variant="record.status_variant" />
                </div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="detail in details" :key="detail.label">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            {{ detail.label }}
                        </dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ detail.value }}</dd>
                    </div>
                    <div v-if="record.remarks" class="sm:col-span-2 lg:col-span-4">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">{{ t('common.notes') }}</dt>
                        <dd class="mt-0.5 text-sm leading-relaxed text-ink-muted">{{ record.remarks }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('delivery.lines_received') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Status dihitung otomatis dari tanggal terima terhadap schedule, dan dari kumulatif
                        quantity terhadap PO.
                    </p>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[58rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">#</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.material') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('po.schedule') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('po.qty') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('po.qty_received') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.condition') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in record.items" :key="item.id" class="border-b border-line/60 last:border-0">
                                <td class="px-5 py-3 text-ink-subtle tabular-nums">{{ item.line_no }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-ink">{{ item.material_name }}</span>
                                    <span class="block text-xs text-ink-subtle">{{ item.material_code }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-ink-muted tabular-nums">
                                    {{ item.schedule_delivery_date ? dateFmt.format(new Date(item.schedule_delivery_date)) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right text-ink-muted tabular-nums">
                                    {{ number.format(item.qty_ordered) }} {{ item.uom_code }}
                                </td>
                                <td class="px-5 py-3 text-right font-medium text-ink tabular-nums">
                                    {{ number.format(item.qty_received) }}
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="item.condition_label" :variant="item.condition_variant" />
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge
                                        :label="item.overall_status_label"
                                        :variant="item.overall_status_variant"
                                    />
                                    <span v-if="item.days_late > 0" class="mt-1 block text-[0.65rem] text-critical">
                                        terlambat {{ item.days_late }} hari
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="cancelling"
                class="fixed inset-0 z-50 flex items-center justify-center bg-canvas/80 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="cancelling = false"
            >
                <form class="card w-full max-w-md p-6" @submit.prevent="cancel">
                    <h2 class="text-base font-semibold text-ink">Batalkan {{ record.delivery_number }}?</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                        Barisnya tetap tercatat, tetapi quantity-nya ditarik kembali dari pemenuhan PO dan
                        delivery ini berhenti dihitung pada KPI.
                    </p>

                    <div class="mt-5">
                        <TextareaInput
                            id="delivery-cancel-reason"
                            v-model="cancelForm.reason"
                            :label="t('common.cancellation_reason')"
                            :error="cancelForm.errors.reason"
                        />
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                            @click="cancelling = false"
                        >{{ t('common.back') }}</button>
                        <button
                            type="submit"
                            class="rounded-lg bg-critical px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60"
                            :disabled="cancelForm.processing"
                        >
                            {{ cancelForm.processing ? 'Memproses…' : 'Batalkan delivery' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
