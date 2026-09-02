<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ResourceIndex, {
    type Column,
    type MasterDataRow,
} from '@/Components/MasterData/ResourceIndex.vue';
import type { Paginated, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{
    records: Paginated<MasterDataRow>;
    filters: { search?: string; status?: string };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const columns: Column[] = [
    {
        key: "code",
        label: "Kode"
    },
    {
        key: "name",
        label: "Nama",
        sub: "short_name"
    },
    {
        key: "city",
        label: "Kota"
    },
    {
        key: "supplier_type_label",
        label: "Tipe"
    },
    {
        key: "lead_time_days",
        label: "Lead Time",
        align: "right",
        numeric: true
    },
    {
        key: "status",
        label: "Status",
        badge: true
    }
];

/** Ziggy's route() is a global, not a component property, so hrefs are
 *  resolved here rather than in the template where vue-tsc cannot see it. */
const showHref = (row: MasterDataRow): string =>
    route('suppliers.show', (row.ulid ?? row.id) as string | number);

const statusOptions: SelectOption[] = [
    { value: 'ACTIVE', label: 'Active' },
    { value: 'INACTIVE', label: 'Inactive' },
];
</script>

<template>
    <ResourceIndex
        :title="t('supplier.index_title')"
        subtitle="Data supplier beserta lead time dan status kerja samanya"
        current="suppliers"
        route-name="suppliers"
        route-key="ulid"
        search-placeholder="Cari kode, nama, atau kota supplier…"
        :columns="columns"
        :records="records"
        :filters="filters"
        :can="can"
        :status-options="statusOptions"
    >
        <template #row-actions="{ row }">
            <Link
                :href="showHref(row)"
                class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-ink"
            >{{ t('common.details') }}</Link>
        </template>
    </ResourceIndex>
</template>
