<script setup lang="ts">
import ResourceIndex, {
    type Column,
    type MasterDataRow,
} from '@/Components/MasterData/ResourceIndex.vue';
import type { Paginated, SelectOption } from '@/Types';

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
        label: "Nama"
    },
    {
        key: "city",
        label: "Kota"
    },
    {
        key: "pic_name",
        label: "PIC"
    },
    {
        key: "warehouses_count",
        label: "Warehouse",
        align: "right",
        numeric: true
    },
    {
        key: "status",
        label: "Status",
        badge: true
    }
];

const statusOptions: SelectOption[] = [
    { value: 'ACTIVE', label: 'Active' },
    { value: 'INACTIVE', label: 'Inactive' },
];
</script>

<template>
    <ResourceIndex
        title="Master Plant"
        subtitle="Lokasi pabrik penerima material"
        current="plants"
        route-name="plants"
        route-key="ulid"
        search-placeholder="Cari kode, nama, atau kota plant…"
        :columns="columns"
        :records="records"
        :filters="filters"
        :can="can"
        :status-options="statusOptions"
    >
    </ResourceIndex>
</template>
