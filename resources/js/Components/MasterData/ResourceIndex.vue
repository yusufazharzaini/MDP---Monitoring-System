<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { BadgeVariant, Paginated, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

export interface Column {
    key: string;
    label: string;
    align?: 'left' | 'right';
    /** Render this cell as a status badge using `${key}_label` and `${key}_variant`. */
    badge?: boolean;
    /** Secondary line beneath the value. */
    sub?: string;
    numeric?: boolean;
}

/** A master-data row as the index table sees it: an id, and whatever else. */
export interface MasterDataRow {
    id: number;
    ulid?: string;
    code?: string;
    name?: string;
    [key: string]: unknown;
}

type Row = MasterDataRow;

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        current: string;
        routeName: string;
        /** Route key: models with a ULID are addressed by it, never by database id. */
        routeKey?: 'id' | 'ulid';
        columns: Column[];
        records: Paginated<Row>;
        filters: { search?: string; status?: string };
        can: { create: boolean; update: boolean; delete: boolean };
        statusOptions?: SelectOption[];
        searchPlaceholder?: string;
    }>(),
    { routeKey: 'id', statusOptions: () => [] },
);

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const pendingDelete = ref<Row | null>(null);
const deleting = ref(false);

let timer: ReturnType<typeof setTimeout> | undefined;

/** Debounced so typing a code fires one request at rest, not one per keystroke. */
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route(`${props.routeName}.index`),
            { search: search.value || undefined, status: status.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});

function keyOf(row: Row): string | number {
    return props.routeKey === 'ulid' ? (row.ulid ?? row.id) : row.id;
}

function confirmDelete(): void {
    if (!pendingDelete.value) {
        return;
    }

    deleting.value = true;
    router.delete(route(`${props.routeName}.destroy`, keyOf(pendingDelete.value)), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            pendingDelete.value = null;
        },
    });
}

function cellValue(row: Row, column: Column): string {
    const value = row[column.key];

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return column.numeric ? new Intl.NumberFormat('id-ID').format(Number(value)) : String(value);
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :current="current" :title="title" :subtitle="subtitle">
        <div class="space-y-5">
            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="flex flex-1 flex-wrap items-center gap-3">
                        <div class="relative min-w-[12rem] flex-1 sm:max-w-xs">
                            <input
                                v-model="search"
                                type="search"
                                class="field-input pl-9"
                                :placeholder="searchPlaceholder ?? 'Cari kode atau nama…'"
                                :aria-label="t('common.search')"
                            />
                            <AppIcon
                                name="filter"
                                :size="14"
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-subtle"
                            />
                        </div>

                        <select
                            v-if="statusOptions.length"
                            v-model="status"
                            class="field-input w-auto min-w-[9rem]"
                            :aria-label="t('filter.status')"
                        >
                            <option value="">{{ t('filter.all_status') }}</option>
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <Link
                        v-if="can.create"
                        :href="route(`${routeName}.create`)"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft"
                    >
                        <AppIcon name="box" :size="15" />{{ t('common.add') }}</Link>
                </header>

                <EmptyState
                    v-if="records.data.length === 0"
                    :title="t('common.no_data')"
                    message="Tidak ada data yang cocok dengan pencarian atau filter Anda."
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th
                                    v-for="column in columns"
                                    :key="column.key"
                                    scope="col"
                                    class="px-5 py-3 font-semibold whitespace-nowrap"
                                    :class="column.align === 'right' ? 'text-right' : 'text-left'"
                                >
                                    {{ column.label }}
                                </th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in records.data"
                                :key="row.id"
                                class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                            >
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="px-5 py-3"
                                    :class="[
                                        column.align === 'right' ? 'text-right' : 'text-left',
                                        column.numeric ? 'tabular-nums' : '',
                                    ]"
                                >
                                    <StatusBadge
                                        v-if="column.badge"
                                        :label="String(row[`${column.key}_label`] ?? row[column.key])"
                                        :variant="(row[`${column.key}_variant`] as BadgeVariant) ?? 'neutral'"
                                    />
                                    <template v-else>
                                        <span class="text-ink">{{ cellValue(row, column) }}</span>
                                        <span v-if="column.sub" class="block text-xs text-ink-subtle">
                                            {{ row[column.sub] ?? '—' }}
                                        </span>
                                    </template>
                                </td>

                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <slot name="row-actions" :row="row" :key-of="keyOf" />
                                        <Link
                                            v-if="can.update"
                                            :href="route(`${routeName}.edit`, keyOf(row))"
                                            class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-info"
                                        >{{ t('common.edit') }}</Link>
                                        <button
                                            v-if="can.delete"
                                            type="button"
                                            class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-critical"
                                            @click="pendingDelete = row"
                                        >{{ t('common.delete') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :meta="records" />
            </section>
        </div>

        <ConfirmDialog
            :open="pendingDelete !== null"
            :title="t('common.delete_confirm')"
            :message="`${pendingDelete?.code ?? pendingDelete?.name ?? ''} akan dihapus. Data yang masih dipakai transaksi berjalan akan ditolak oleh sistem.`"
            :processing="deleting"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
    </AppLayout>
</template>
