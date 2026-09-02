<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { BadgeVariant, Paginated, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

interface Row {
    id: number;
    ulid: string;
    po_number: string;
    po_date: string;
    supplier_name: string | null;
    supplier_code: string | null;
    plant_name: string | null;
    items_count: number;
    total_amount: number;
    currency: string;
    status_label: string;
    status_variant: BadgeVariant;
}

const props = defineProps<{
    records: Paginated<Row>;
    filters: { search?: string; status?: string; supplier_id?: number; plant_id?: number };
    options: { statuses: SelectOption[]; suppliers: SelectOption[]; plants: SelectOption[] };
    can: { create: boolean };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const supplierId = ref(props.filters.supplier_id ?? '');
const plantId = ref(props.filters.plant_id ?? '');

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const date = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

let timer: ReturnType<typeof setTimeout> | undefined;

watch([search, status, supplierId, plantId], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('purchase-orders.index'),
            {
                search: search.value || undefined,
                status: status.value || undefined,
                supplier_id: supplierId.value || undefined,
                plant_id: plantId.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head :title="t('po.title')" />

    <AppLayout
        current="purchase-orders"
        :title="t('po.title')"
        subtitle="Komitmen pembelian material dari supplier"
    >
        <section class="card">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <div class="relative min-w-[11rem] flex-1 sm:max-w-xs">
                        <input
                            v-model="search"
                            type="search"
                            class="field-input pl-9"
                            :placeholder="t('po.search_placeholder')"
                            :aria-label="t('po.search')"
                        />
                        <AppIcon
                            name="filter"
                            :size="14"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-subtle"
                        />
                    </div>

                    <select v-model="status" class="field-input w-auto min-w-[9rem]" :aria-label="t('filter.status')">
                        <option value="">{{ t('filter.all_status') }}</option>
                        <option v-for="option in options.statuses" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <select v-model="supplierId" class="field-input w-auto min-w-[11rem]" :aria-label="t('filter.supplier')">
                        <option value="">{{ t('filter.all_suppliers') }}</option>
                        <option v-for="option in options.suppliers" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <select v-model="plantId" class="field-input w-auto min-w-[10rem]" :aria-label="t('filter.plant')">
                        <option value="">{{ t('filter.all_plants') }}</option>
                        <option v-for="option in options.plants" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <Link
                    v-if="can.create"
                    :href="route('purchase-orders.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft"
                >
                    <AppIcon name="order" :size="15" />{{ t('po.create') }}</Link>
            </header>

            <EmptyState
                v-if="records.data.length === 0"
                :title="t('po.none')"
                message="Tidak ada PO yang cocok dengan filter Anda."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('po.number') }}</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.date') }}</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.supplier') }}</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.plant') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('common.item') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('po.value') }}</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in records.data"
                            :key="row.id"
                            class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                        >
                            <td class="px-5 py-3 font-medium whitespace-nowrap text-ink">{{ row.po_number }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-ink-muted tabular-nums">
                                {{ date.format(new Date(row.po_date)) }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-ink">{{ row.supplier_name }}</span>
                                <span class="block text-xs text-ink-subtle">{{ row.supplier_code }}</span>
                            </td>
                            <td class="px-5 py-3 text-ink-muted">{{ row.plant_name }}</td>
                            <td class="px-5 py-3 text-right text-ink tabular-nums">{{ row.items_count }}</td>
                            <td class="px-5 py-3 text-right text-ink tabular-nums">
                                {{ row.currency }} {{ money.format(row.total_amount) }}
                            </td>
                            <td class="px-5 py-3">
                                <StatusBadge :label="row.status_label" :variant="row.status_variant" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <Link
                                    :href="route('purchase-orders.show', row.ulid)"
                                    class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-info"
                                >{{ t('common.details') }}</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="records" />
        </section>
    </AppLayout>
</template>
