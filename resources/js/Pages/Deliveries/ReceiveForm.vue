<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import type { DeliveryRecord, ReceivingContext, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    purchaseOrder: ReceivingContext;
    options: { conditions: SelectOption[] };
    record?: DeliveryRecord;
}>();

const isEdit = computed(() => props.record !== undefined);
const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });
const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

interface EditableLine {
    purchase_order_item_id: number;
    include: boolean;
    qty_received: number;
    condition: string;
}

/**
 * Every order line is listed; the clerk ticks the ones this delivery actually
 * brought. A line already booked by the receipt being corrected starts ticked
 * with its own quantity.
 */
const lines = ref<EditableLine[]>(
    props.purchaseOrder.lines.map((line) => ({
        purchase_order_item_id: line.purchase_order_item_id,
        include: line.booked_here !== null || (!props.record && line.outstanding > 0),
        qty_received: line.booked_here ?? line.outstanding,
        condition: line.booked_condition ?? 'GOOD',
    })),
);

const form = useForm({
    delivery_date: props.record?.delivery_date ?? new Date().toISOString().slice(0, 10),
    do_number: props.record?.do_number ?? '',
    vehicle_number: props.record?.vehicle_number ?? '',
    driver_name: props.record?.driver_name ?? '',
    remarks: props.record?.remarks ?? '',
    items: [] as Array<Omit<EditableLine, 'include'>>,
});

const includedCount = computed(() => lines.value.filter((line) => line.include).length);

function submit(): void {
    form.items = lines.value
        .filter((line) => line.include)
        .map(({ purchase_order_item_id, qty_received, condition }) => ({
            purchase_order_item_id,
            qty_received,
            condition,
        }));

    if (props.record) {
        form.put(route('deliveries.update', props.record.ulid));
    } else {
        form.post(route('deliveries.store', props.purchaseOrder.ulid));
    }
}

const backHref = computed(() =>
    props.record
        ? route('deliveries.show', props.record.ulid)
        : route('purchase-orders.show', props.purchaseOrder.ulid),
);

/** Flags a quantity above what is still outstanding, before the server does. */
function isOver(index: number): boolean {
    const context = props.purchaseOrder.lines[index];

    return lines.value[index].include && lines.value[index].qty_received > context.outstanding;
}
</script>

<template>
    <Head :title="isEdit ? `Koreksi ${record?.delivery_number}` : `Terima ${purchaseOrder.po_number}`" />

    <AppLayout
        current="deliveries"
        :title="isEdit ? `Koreksi ${record?.delivery_number}` : 'Penerimaan Material'"
        :subtitle="`${purchaseOrder.po_number} · ${purchaseOrder.supplier_name ?? ''} · ${purchaseOrder.plant_name ?? ''}`"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-lg bg-critical-ground px-4 py-3 text-sm text-critical ring-1 ring-critical/30"
                role="alert"
            >{{ t('msg.check_marked_fields') }}</div>

            <section class="card p-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <TextInput
                        id="delivery_date"
                        v-model="form.delivery_date"
                        type="date"
                        label="Tanggal Terima"
                        required
                        :error="form.errors.delivery_date"
                        hint="Tanggal barang benar-benar tiba"
                    />
                    <TextInput id="do_number" v-model="form.do_number" label="No. Surat Jalan" :error="form.errors.do_number" />
                    <TextInput id="vehicle_number" v-model="form.vehicle_number" label="No. Kendaraan" :error="form.errors.vehicle_number" />
                    <TextInput id="driver_name" v-model="form.driver_name" label="Nama Driver" :error="form.errors.driver_name" />
                    <div class="sm:col-span-2 lg:col-span-4">
                        <TextareaInput id="remarks" v-model="form.remarks" :label="t('common.notes')" :error="form.errors.remarks" />
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Baris Penerimaan</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            Centang baris yang benar-benar diterima pada pengiriman ini.
                        </p>
                    </div>
                    <StatusBadge :label="`${includedCount} baris dipilih`" variant="info" :icon="false" />
                </header>

                <p v-if="form.errors.items" class="px-5 pt-4 text-xs text-critical" role="alert">
                    {{ form.errors.items }}
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[62rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-4 py-3 text-left font-semibold">Terima</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold">{{ t('entity.material') }}</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold">{{ t('po.schedule') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('po.qty') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Sudah Diterima</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Sisa</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('po.qty_received') }}</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold">{{ t('common.condition') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(context, index) in purchaseOrder.lines"
                                :key="context.purchase_order_item_id"
                                class="border-b border-line/60 last:border-0"
                                :class="lines[index].include ? '' : 'opacity-50'"
                            >
                                <td class="px-4 py-3">
                                    <input
                                        :id="`include-${index}`"
                                        v-model="lines[index].include"
                                        type="checkbox"
                                        class="size-4 rounded border-line bg-canvas text-brand"
                                        :aria-label="`Terima baris ${context.line_no}`"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-ink">{{ context.material_name }}</span>
                                    <span class="block text-xs text-ink-subtle">
                                        {{ context.material_code }} · {{ context.warehouse_name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-muted tabular-nums">
                                    {{ dateFmt.format(new Date(context.schedule_delivery_date)) }}
                                </td>
                                <td class="px-4 py-3 text-right text-ink-muted tabular-nums">
                                    {{ number.format(context.qty_ordered) }} {{ context.uom_code }}
                                </td>
                                <td class="px-4 py-3 text-right text-ink-muted tabular-nums">
                                    {{ number.format(context.qty_received) }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums"
                                    :class="context.outstanding > 0 ? 'text-warning' : 'text-ink-subtle'">
                                    {{ number.format(context.outstanding) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        v-model.number="lines[index].qty_received"
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        class="field-input w-28 text-right tabular-nums"
                                        :class="isOver(index) ? 'border-warning' : ''"
                                        :disabled="!lines[index].include"
                                        :aria-label="`Quantity diterima baris ${context.line_no}`"
                                    />
                                    <p v-if="isOver(index)" class="mt-1 text-[0.65rem] text-warning">
                                        Melebihi sisa PO
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        v-model="lines[index].condition"
                                        class="field-input min-w-[8rem]"
                                        :disabled="!lines[index].include"
                                        :aria-label="`Kondisi baris ${context.line_no}`"
                                    >
                                        <option v-for="c in options.conditions" :key="c.value" :value="c.value">
                                            {{ c.label }}
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-muted">
                    Barang berkondisi <strong>Rejected</strong> tetap tercatat tetapi tidak dihitung sebagai
                    pemenuhan PO. Penerimaan melebihi sisa PO diperbolehkan dan akan ditandai sebagai
                    <strong>Over Delivery</strong>.
                </p>
            </section>

            <div class="flex items-center justify-end gap-2">
                <Link
                    :href="backHref"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >{{ t('common.cancel') }}</Link>
                <button
                    type="submit"
                    class="rounded-lg bg-brand px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-60"
                    :disabled="form.processing || includedCount === 0"
                >
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan koreksi' : 'Catat penerimaan' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
