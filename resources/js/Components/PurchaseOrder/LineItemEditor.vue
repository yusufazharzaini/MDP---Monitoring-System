<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import type { PurchaseOrderFormOptions, PurchaseOrderLine, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    options: PurchaseOrderFormOptions;
    plantId: number | null;
    poDate: string;
    errors: Record<string, string>;
}>();

const lines = defineModel<PurchaseOrderLine[]>({ required: true });

const currency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/**
 * Only warehouses in the chosen plant are offered. The backend enforces the
 * same rule, but offering an impossible choice and then rejecting it is a poor
 * way to explain a constraint.
 */
const warehouseOptions = computed<SelectOption[]>(() =>
    props.options.warehouses.filter((warehouse) => warehouse.plant_id === props.plantId),
);

function blankLine(): PurchaseOrderLine {
    return {
        id: null,
        material_id: null,
        warehouse_id: warehouseOptions.value.length === 1 ? Number(warehouseOptions.value[0].value) : null,
        uom_id: null,
        schedule_delivery_date: props.poDate,
        qty_ordered: 1,
        unit_price: 0,
        remarks: null,
    };
}

function addLine(): void {
    lines.value = [...lines.value, blankLine()];
}

function removeLine(index: number): void {
    lines.value = lines.value.filter((_, position) => position !== index);
}

/** Picking a material pre-fills its default unit, which is right nine times in ten. */
function onMaterialChange(line: PurchaseOrderLine, value: string): void {
    const id = value === '' ? null : Number(value);
    line.material_id = id;

    const material = props.options.materials.find((m) => m.value === id);

    if (material && line.uom_id === null) {
        line.uom_id = material.uom_id;
    }
}

const lineTotal = (line: PurchaseOrderLine): number =>
    Number(line.qty_ordered || 0) * Number(line.unit_price || 0);

/** Preview only - the authoritative total is recomputed server-side on save. */
const grandTotal = computed(() => lines.value.reduce((sum, line) => sum + lineTotal(line), 0));

function errorFor(index: number, field: string): string | undefined {
    return props.errors[`items.${index}.${field}`];
}

const receivedOn = (line: PurchaseOrderLine): number => Number(line.qty_received ?? 0);
</script>

<template>
    <section class="card">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('po.items') }}</h2>
                <p class="mt-0.5 text-xs text-ink-muted">{{ t('po.lines_hint') }}</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink-muted transition hover:border-info hover:text-info disabled:opacity-50"
                :disabled="plantId === null"
                :title="plantId === null ? 'Pilih plant terlebih dahulu' : undefined"
                @click="addLine"
            >
                <AppIcon name="box" :size="14" />{{ t('po.add_line') }}</button>
        </header>

        <p v-if="errors.items" class="px-5 pt-4 text-xs text-critical" role="alert">{{ errors.items }}</p>

        <div v-if="lines.length === 0" class="px-5 py-10 text-center text-sm text-ink-muted">{{ t('po.no_lines') }}</div>

        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[64rem] text-sm">
                <thead>
                    <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                        <th scope="col" class="px-4 py-3 text-left font-semibold">#</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ t('entity.material') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ t('entity.warehouse') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ t('common.unit') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ t('po.schedule') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('po.qty_short') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('po.price') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('common.quantity') }}</th>
                        <th scope="col" class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(line, index) in lines"
                        :key="line.id ?? `new-${index}`"
                        class="border-b border-line/60 last:border-0"
                    >
                        <td class="px-4 py-3 align-top text-ink-subtle tabular-nums">{{ index + 1 }}</td>

                        <td class="px-4 py-3 align-top">
                            <select
                                class="field-input min-w-[13rem]"
                                :class="errorFor(index, 'material_id') ? 'border-critical' : ''"
                                :value="line.material_id ?? ''"
                                :aria-label="`Material baris ${index + 1}`"
                                @change="onMaterialChange(line, ($event.target as HTMLSelectElement).value)"
                            >
                                <option value="">{{ t('select.material') }}</option>
                                <option v-for="m in options.materials" :key="m.value" :value="m.value">
                                    {{ m.label }}
                                </option>
                            </select>
                            <p v-if="errorFor(index, 'material_id')" class="mt-1 text-xs text-critical">
                                {{ errorFor(index, 'material_id') }}
                            </p>
                        </td>

                        <td class="px-4 py-3 align-top">
                            <select
                                v-model.number="line.warehouse_id"
                                class="field-input min-w-[11rem]"
                                :class="errorFor(index, 'warehouse_id') ? 'border-critical' : ''"
                                :aria-label="`Warehouse baris ${index + 1}`"
                            >
                                <option :value="null">{{ t('select.warehouse') }}</option>
                                <option v-for="w in warehouseOptions" :key="w.value" :value="w.value">
                                    {{ w.label }}
                                </option>
                            </select>
                            <p v-if="errorFor(index, 'warehouse_id')" class="mt-1 text-xs text-critical">
                                {{ errorFor(index, 'warehouse_id') }}
                            </p>
                        </td>

                        <td class="px-4 py-3 align-top">
                            <select
                                v-model.number="line.uom_id"
                                class="field-input min-w-[6rem]"
                                :aria-label="`Satuan baris ${index + 1}`"
                            >
                                <option :value="null">—</option>
                                <option v-for="u in options.uoms" :key="u.value" :value="u.value">{{ u.label }}</option>
                            </select>
                        </td>

                        <td class="px-4 py-3 align-top">
                            <input
                                v-model="line.schedule_delivery_date"
                                type="date"
                                class="field-input min-w-[9rem]"
                                :class="errorFor(index, 'schedule_delivery_date') ? 'border-critical' : ''"
                                :aria-label="`Schedule baris ${index + 1}`"
                            />
                            <p v-if="errorFor(index, 'schedule_delivery_date')" class="mt-1 text-xs text-critical">
                                {{ errorFor(index, 'schedule_delivery_date') }}
                            </p>
                        </td>

                        <td class="px-4 py-3 text-right align-top">
                            <input
                                v-model.number="line.qty_ordered"
                                type="number"
                                min="0"
                                step="0.0001"
                                class="field-input w-28 text-right tabular-nums"
                                :class="errorFor(index, 'qty_ordered') ? 'border-critical' : ''"
                                :aria-label="`Quantity baris ${index + 1}`"
                            />
                            <p v-if="receivedOn(line) > 0" class="mt-1 text-[0.65rem] text-ink-subtle">
                                diterima {{ currency.format(receivedOn(line)) }}
                            </p>
                            <p v-if="errorFor(index, 'qty_ordered')" class="mt-1 text-xs text-critical">
                                {{ errorFor(index, 'qty_ordered') }}
                            </p>
                        </td>

                        <td class="px-4 py-3 text-right align-top">
                            <input
                                v-model.number="line.unit_price"
                                type="number"
                                min="0"
                                step="0.0001"
                                class="field-input w-32 text-right tabular-nums"
                                :aria-label="`Harga baris ${index + 1}`"
                            />
                        </td>

                        <td class="px-4 py-3 text-right align-top font-medium text-ink tabular-nums">
                            {{ currency.format(lineTotal(line)) }}
                        </td>

                        <td class="px-4 py-3 align-top">
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-critical disabled:opacity-40"
                                :disabled="receivedOn(line) > 0"
                                :title="receivedOn(line) > 0 ? 'Baris yang sudah menerima barang tidak dapat dihapus' : undefined"
                                @click="removeLine(index)"
                            >{{ t('common.delete') }}</button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-line">
                        <td colspan="7" class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ t('po.estimated_total') }}</td>
                        <td class="px-4 py-3 text-right text-base font-semibold text-ink tabular-nums">
                            {{ currency.format(grandTotal) }}
                        </td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</template>
