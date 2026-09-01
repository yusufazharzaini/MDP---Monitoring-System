<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type {
    DashboardFilters,
    SelectOption,
    SupplierGradeBand,
    SupplierPerformanceRow,
} from '@/Types';

const props = defineProps<{
    filters: DashboardFilters;
    ranking: SupplierPerformanceRow[];
    /** Read from kpi_settings so the legend and the grades move together. */
    thresholds: SupplierGradeBand[];
    options: { plants: SelectOption[]; materialCategories: SelectOption[] };
}>();

const period = ref(props.filters.period);
const plantId = ref(props.filters.plant_id ?? '');
const categoryId = ref(props.filters.material_category_id ?? '');

const number = new Intl.NumberFormat('id-ID');
const rate = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

function bandCaption(band: SupplierGradeBand): string {
    return band.ceiling === null
        ? `≥ ${rate.format(band.floor)}%`
        : `${rate.format(band.floor)}–${rate.format(band.ceiling)}%`;
}

let timer: ReturnType<typeof setTimeout> | undefined;

watch([period, plantId, categoryId], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('supplier-performance.index'),
            {
                period: period.value || undefined,
                plant_id: plantId.value || undefined,
                material_category_id: categoryId.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head title="Supplier Performance" />

    <AppLayout
        current="supplier-performance"
        title="Supplier Performance"
        subtitle="Peringkat service rate seluruh supplier pada periode terpilih"
    >
        <div class="space-y-5">
            <section class="card">
                <header class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                    <label class="flex items-center gap-2 text-sm text-ink-muted">
                        Periode
                        <input v-model="period" type="month" class="field-input w-auto" aria-label="Periode" />
                    </label>

                    <select v-model="plantId" class="field-input w-auto min-w-[10rem]" aria-label="Filter plant">
                        <option value="">Semua plant</option>
                        <option v-for="option in options.plants" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <select v-model="categoryId" class="field-input w-auto min-w-[11rem]" aria-label="Filter kategori material">
                        <option value="">Semua kategori</option>
                        <option v-for="option in options.materialCategories" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <!-- The bands are data, not a hard-coded legend. -->
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <span
                            v-for="band in thresholds"
                            :key="band.grade"
                            class="flex items-center gap-1.5 text-xs text-ink-subtle"
                        >
                            <StatusBadge :label="band.label" :variant="band.variant" />
                            <span class="tabular-nums">{{ bandCaption(band) }}</span>
                        </span>
                    </div>
                </header>

                <EmptyState
                    v-if="ranking.length === 0"
                    title="Tidak ada penerimaan pada periode ini"
                    message="Supplier tanpa penerimaan tidak diperingkat, karena 0% berarti tidak ada data — bukan performa buruk."
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">Rank</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">Supplier</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Delivery</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">On Time</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Terlambat</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Kurang</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Service Rate</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">Grade</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in ranking"
                                :key="row.supplier_id"
                                class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                            >
                                <td class="px-5 py-3 text-ink-muted tabular-nums">{{ row.rank }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-ink">{{ row.supplier_name }}</p>
                                    <p class="text-xs text-ink-subtle">{{ row.supplier_code }}</p>
                                </td>
                                <td class="px-5 py-3 text-right text-ink tabular-nums">
                                    {{ number.format(row.total_delivery) }}
                                </td>
                                <td class="px-5 py-3 text-right text-ink-muted tabular-nums">
                                    {{ number.format(row.on_time_delivery) }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums" :class="row.late_delivery > 0 ? 'text-warning' : 'text-ink-muted'">
                                    {{ number.format(row.late_delivery) }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums" :class="row.short_delivery > 0 ? 'text-serious' : 'text-ink-muted'">
                                    {{ number.format(row.short_delivery) }}
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-ink tabular-nums">
                                    {{ rate.format(row.service_rate) }}%
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="row.grade_label" :variant="row.grade_variant" />
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <Link
                                        :href="route('supplier-performance.show', row.supplier_ulid)"
                                        class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-info"
                                    >
                                        Scorecard
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
