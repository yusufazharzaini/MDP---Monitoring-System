<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import LineItemEditor from '@/Components/PurchaseOrder/LineItemEditor.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import type { PurchaseOrderFormOptions, PurchaseOrderLine, PurchaseOrderRecord } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    options: PurchaseOrderFormOptions;
    record?: PurchaseOrderRecord;
}>();

const isEdit = computed(() => props.record !== undefined);

const form = useForm({
    po_date: props.record?.po_date ?? new Date().toISOString().slice(0, 10),
    supplier_id: props.record?.supplier_id ?? (null as number | null),
    plant_id: props.record?.plant_id ?? (null as number | null),
    currency: props.record?.currency ?? 'IDR',
    payment_term: props.record?.payment_term ?? 'NET 30',
    remarks: props.record?.remarks ?? '',
    items: (props.record?.items ?? []) as PurchaseOrderLine[],
});

/**
 * A warehouse belongs to exactly one plant, so changing the plant invalidates
 * every line's destination. Clearing them is honest; leaving them would submit
 * a combination the server must reject.
 */
watch(
    () => form.plant_id,
    (next, previous) => {
        if (previous !== null && next !== previous) {
            form.items = form.items.map((line) => ({ ...line, warehouse_id: null }));
        }
    },
);

const backHref = computed(() =>
    props.record ? route('purchase-orders.show', props.record.ulid) : route('purchase-orders.index'),
);

function submit(): void {
    if (props.record) {
        form.put(route('purchase-orders.update', props.record.ulid));
    } else {
        form.post(route('purchase-orders.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? `Ubah ${record?.po_number}` : 'Buat Purchase Order'" />

    <AppLayout
        current="purchase-orders"
        :title="isEdit ? `Ubah ${record?.po_number}` : 'Buat Purchase Order'"
        :subtitle="isEdit ? 'Perubahan hanya dimungkinkan selama PO belum disetujui.' : 'PO baru selalu tersimpan sebagai draft.'"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-lg bg-critical-ground px-4 py-3 text-sm text-critical ring-1 ring-critical/30"
                role="alert"
            >{{ t('msg.check_marked_fields') }}</div>

            <section class="card p-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <TextInput
                        id="po_date"
                        v-model="form.po_date"
                        type="date"
                        label="Tanggal PO"
                        required
                        :error="form.errors.po_date"
                    />
                    <SelectInput
                        id="supplier_id"
                        v-model="form.supplier_id"
                        :label="t('entity.supplier')"
                        required
                        numeric
                        placeholder="Pilih supplier"
                        :options="options.suppliers"
                        :error="form.errors.supplier_id"
                    />
                    <SelectInput
                        id="plant_id"
                        v-model="form.plant_id"
                        :label="t('entity.plant')"
                        required
                        numeric
                        :placeholder="t('select.plant')"
                        :options="options.plants"
                        :error="form.errors.plant_id"
                    />
                    <TextInput
                        id="currency"
                        v-model="form.currency"
                        label="Mata Uang"
                        required
                        :error="form.errors.currency"
                    />
                    <TextInput
                        id="payment_term"
                        v-model="form.payment_term"
                        :label="t('po.payment_term')"
                        :error="form.errors.payment_term"
                    />
                    <div class="sm:col-span-2 lg:col-span-3">
                        <TextareaInput
                            id="remarks"
                            v-model="form.remarks"
                            :label="t('common.notes')"
                            :error="form.errors.remarks"
                        />
                    </div>
                </div>
            </section>

            <LineItemEditor
                v-model="form.items"
                :options="options"
                :plant-id="form.plant_id"
                :po-date="form.po_date"
                :errors="form.errors as Record<string, string>"
            />

            <div class="flex items-center justify-end gap-2">
                <Link
                    :href="backHref"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >{{ t('common.cancel') }}</Link>
                <button
                    type="submit"
                    class="rounded-lg bg-brand px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan perubahan' : 'Simpan sebagai draft' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
