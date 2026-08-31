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
        key: "materials_count",
        label: "Material",
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
        title="Kategori Material"
        subtitle="Pengelompokan material untuk analisa dan filter dashboard"
        current="materials"
        route-name="material-categories"
        route-key="id"
        search-placeholder="Cari kode atau nama kategori…"
        :columns="columns"
        :records="records"
        :filters="filters"
        :can="can"
        :status-options="statusOptions"
    >
    </ResourceIndex>
</template>
