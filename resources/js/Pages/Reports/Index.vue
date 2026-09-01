<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();
import type {
    DashboardFilters,
    ReportCatalogueEntry,
    ReportColumnMeta,
    SelectOption,
} from '@/Types';

const props = defineProps<{
    catalogue: ReportCatalogueEntry[];
    selected: string;
    filters: DashboardFilters;
    formats: string[];
    columns: ReportColumnMeta[];
    preview: Array<Record<string, string | number | null>>;
    previewLimit: number;
    options: { suppliers: SelectOption[]; plants: SelectOption[]; materialCategories: SelectOption[] };
    can: { export: boolean };
}>();

const type = ref(props.selected);
const period = ref(props.filters.period);
const supplierId = ref(props.filters.supplier_id ?? '');
const plantId = ref(props.filters.plant_id ?? '');
const categoryId = ref(props.filters.material_category_id ?? '');

/** Counts and ranks print whole; measured values keep their decimals. */
function formatNumber(value: unknown, decimals: number): string {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number(value ?? 0));
}

const active = computed(() => props.catalogue.find((entry) => entry.value === props.selected));

/** Every download carries the filters the preview was built from. */
const exportQuery = computed(() => ({
    period: period.value || undefined,
    supplier_id: supplierId.value || undefined,
    plant_id: plantId.value || undefined,
    material_category_id: categoryId.value || undefined,
}));

const formatLabels: Record<string, string> = {
    xlsx: 'Excel',
    csv: 'CSV',
    pdf: 'PDF',
    print: 'Cetak',
};

function exportHref(format: string): string {
    return route('reports.export', { type: props.selected, format, ...exportQuery.value });
}

let timer: ReturnType<typeof setTimeout> | undefined;

watch([type, period, supplierId, plantId, categoryId], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('reports.index'),
            { type: type.value, ...exportQuery.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head title="Report" />

    <AppLayout
        current="reports"
        title="Report"
        subtitle="Laporan delivery, purchase order, supplier, problem, dan material"
    >
        <div class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="entry in catalogue"
                    :key="entry.value"
                    type="button"
                    class="card p-4 text-left transition"
                    :class="
                        entry.value === selected
                            ? 'ring-1 ring-brand/60'
                            : 'hover:bg-surface-hover'
                    "
                    :aria-pressed="entry.value === selected"
                    @click="type = entry.value"
                >
                    <p class="text-sm font-semibold text-ink">{{ entry.label }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-ink-muted">{{ entry.description }}</p>
                </button>
            </div>

            <section class="card">
                <header class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                    <label class="flex items-center gap-2 text-sm text-ink-muted">{{ t('common.period') }}<input v-model="period" type="month" class="field-input w-auto" aria-label="Periode laporan" />
                    </label>

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

                    <select v-model="categoryId" class="field-input w-auto min-w-[11rem]" :aria-label="t('filter.material_category')">
                        <option value="">{{ t('filter.all_categories') }}</option>
                        <option v-for="option in options.materialCategories" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <div v-if="can.export" class="ml-auto flex flex-wrap items-center gap-2">
                        <!--
                            Plain anchors, not Inertia visits: each response is a
                            file or a printable document, not a page.
                        -->
                        <a
                            v-for="format in formats"
                            :key="format"
                            :href="exportHref(format)"
                            :target="format === 'print' ? '_blank' : undefined"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                        >
                            <AppIcon :name="format === 'print' ? 'report' : 'box'" :size="14" />
                            {{ formatLabels[format] ?? format }}
                        </a>
                    </div>
                    <p v-else class="ml-auto text-xs text-ink-subtle">
                        Akun Anda dapat melihat laporan tetapi tidak mengunduhnya.
                    </p>
                </header>

                <div class="border-b border-line px-5 py-3">
                    <p class="text-sm font-semibold text-ink">{{ active?.label }}</p>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Pratinjau {{ preview.length }} baris pertama
                        <span v-if="preview.length >= previewLimit">
                            (dibatasi {{ previewLimit }}) &mdash; unduh untuk data lengkap
                        </span>
                        &middot; periode {{ filters.date_from }} s/d {{ filters.date_to }}
                    </p>
                </div>

                <EmptyState
                    v-if="preview.length === 0"
                    title="Tidak ada data"
                    message="Tidak ada baris pada periode dan filter yang dipilih."
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th
                                    v-for="column in columns"
                                    :key="column.key"
                                    scope="col"
                                    class="px-4 py-3 font-semibold whitespace-nowrap"
                                    :class="column.numeric ? 'text-right' : 'text-left'"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in preview"
                                :key="index"
                                class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                            >
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="px-4 py-2.5 whitespace-nowrap"
                                    :class="column.numeric ? 'text-right text-ink tabular-nums' : 'text-ink-muted'"
                                >
                                    <template v-if="column.numeric">
                                        {{ formatNumber(row[column.key], column.decimals) }}
                                    </template>
                                    <template v-else>
                                        {{ row[column.key] ?? '—' }}
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
