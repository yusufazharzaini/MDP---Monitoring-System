<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, defineAsyncComponent } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DashboardCard from '@/Components/DashboardCard.vue';
import ErrorState from '@/Components/ErrorState.vue';
import FilterBar from '@/Components/FilterBar.vue';
import PanelCard from '@/Components/PanelCard.vue';
import PoMonitoringTable from '@/Components/PoMonitoringTable.vue';
import SkeletonBlock from '@/Components/SkeletonBlock.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import SupplierPerformanceTable from '@/Components/SupplierPerformanceTable.vue';
import { useDashboard } from '@/Composables/useDashboard';
import type { BadgeVariant, DashboardFilterOptions, DashboardPayload } from '@/types';

/**
 * ECharts is by far the heaviest dependency on this page. Loading the charts
 * asynchronously lets the shell, the filter bar and the KPI cards paint first,
 * and keeps the charting library out of the initial bundle entirely.
 */
const ServiceRateChart = defineAsyncComponent(
    () => import('@/Components/Charts/ServiceRateChart.vue'),
);
const ParetoChart = defineAsyncComponent(() => import('@/Components/Charts/ParetoChart.vue'));

const props = defineProps<{
    dashboard: DashboardPayload;
    options: DashboardFilterOptions;
    generatedAt: string;
}>();

const { payload, generatedAt, loading, error, filters, refresh, resetFilters, hasActiveFilters } =
    useDashboard(props.dashboard, props.generatedAt);

const number = new Intl.NumberFormat('id-ID');
const summary = computed(() => payload.value.summary);

/**
 * The six KPI cards. Every value is read straight from the payload - the only
 * work done here is formatting and choosing which status colour and word the
 * card wears.
 */
const cards = computed(() => {
    const s = summary.value;
    const severityVariant: Record<string, BadgeVariant> = {
        success: 'success',
        warning: 'warning',
        critical: 'danger',
        info: 'info',
    };

    return [
        {
            key: 'service-rate',
            label: 'Service Rate',
            value: s.service_rate.toFixed(1),
            unit: '%',
            variant: severityVariant[s.severity] ?? 'info',
            icon: 'trend',
            hero: true,
            statusLabel: s.target_met ? 'Memenuhi target' : 'Di bawah target',
            caption: `Target ${s.target}%`,
        },
        {
            key: 'total-delivery',
            label: 'Total Delivery',
            value: number.format(s.total_delivery),
            variant: 'info' as BadgeVariant,
            icon: 'truck',
            caption: 'Baris penerimaan pada periode ini',
        },
        {
            key: 'on-time',
            label: 'On Time Delivery',
            value: number.format(s.on_time_delivery),
            variant: 'success' as BadgeVariant,
            icon: 'good',
            statusLabel: `${s.on_time_rate.toFixed(1)}%`,
            caption: 'dari total delivery',
        },
        {
            key: 'late',
            label: 'Late Delivery',
            value: number.format(s.late_delivery),
            variant: (s.late_delivery > 0 ? 'danger' : 'success') as BadgeVariant,
            icon: 'clock',
            statusLabel: `${s.late_rate.toFixed(1)}%`,
            caption: 'dari total delivery',
        },
        {
            key: 'short',
            label: 'Short Delivery',
            value: number.format(s.short_delivery),
            variant: (s.short_delivery > 0 ? 'warning' : 'success') as BadgeVariant,
            icon: 'warning',
            caption: `Kurang ${number.format(Math.round(s.quantity_shortage))} qty`,
        },
        {
            key: 'critical-material',
            label: 'Critical Material',
            value: number.format(s.critical_material),
            variant: (s.critical_material > 0 ? 'danger' : 'success') as BadgeVariant,
            icon: 'critical',
            caption: s.critical_material > 0 ? 'Perlu perhatian' : 'Tidak ada',
        },
    ];
});

const periodLabel = computed(() =>
    new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' })
        .format(new Date(`${payload.value.filters.period}-01T00:00:00`)),
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout
        current="dashboard"
        title="Service Rate Delivery Material"
        :subtitle="`Monitor ketepatan delivery material dari supplier — periode ${periodLabel}`"
        :generated-at="generatedAt"
        :refreshing="loading"
        @refresh="refresh"
    >
        <div class="space-y-5">
            <FilterBar
                v-model="filters"
                :options="options"
                :has-active-filters="hasActiveFilters"
                :loading="loading"
                @reset="resetFilters"
            />

            <!-- A failed fetch replaces the panels rather than leaving stale numbers on screen. -->
            <div v-if="error" class="card">
                <ErrorState :message="error" @retry="refresh" />
            </div>

            <template v-else>
                <!-- KPI cards -->
                <section aria-label="Ringkasan KPI">
                    <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                        <div v-for="n in 6" :key="n" class="card space-y-4 p-5">
                            <SkeletonBlock height="0.7rem" width="55%" />
                            <SkeletonBlock height="2.25rem" width="70%" />
                            <SkeletonBlock height="0.7rem" width="45%" />
                        </div>
                    </div>

                    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                        <DashboardCard
                            v-for="card in cards"
                            :key="card.key"
                            :label="card.label"
                            :value="card.value"
                            :unit="card.unit"
                            :caption="card.caption"
                            :variant="card.variant"
                            :icon="card.icon"
                            :hero="card.hero"
                            :status-label="card.statusLabel"
                            :class="card.hero ? '2xl:col-span-1' : ''"
                        />
                    </div>
                </section>

                <!-- Trend + supplier ranking -->
                <div class="grid gap-5 xl:grid-cols-2">
                    <PanelCard
                        title="Trend Service Rate"
                        subtitle="Enam bulan terakhir terhadap target"
                        :loading="loading"
                        :empty="payload.trend.every((point) => point.total_delivery === 0)"
                        empty-message="Belum ada delivery pada rentang enam bulan ini."
                        @retry="refresh"
                    >
                        <div class="p-4">
                            <ServiceRateChart :points="payload.trend" :target="summary.target" />
                        </div>
                    </PanelCard>

                    <PanelCard
                        title="Supplier Performance"
                        subtitle="Peringkat berdasarkan on time delivery"
                        :loading="loading"
                        :empty="payload.supplier_performance.length === 0"
                        empty-message="Tidak ada supplier yang mengirim pada periode ini."
                        @retry="refresh"
                    >
                        <SupplierPerformanceTable
                            :rows="payload.supplier_performance"
                            :target="summary.target"
                        />
                    </PanelCard>
                </div>

                <!-- Pareto + critical materials -->
                <div class="grid gap-5 xl:grid-cols-2">
                    <PanelCard
                        title="Pareto Masalah Delivery"
                        :subtitle="`${payload.pareto.total_problems} masalah — ${payload.pareto.vital_few_count} kategori menyumbang mayoritas`"
                        :loading="loading"
                        :empty="payload.pareto.categories.length === 0"
                        empty-message="Tidak ada masalah delivery yang tercatat pada periode ini."
                        @retry="refresh"
                    >
                        <div class="space-y-4 p-4">
                            <ParetoChart :dataset="payload.pareto" />

                            <!-- The value channel for the chart: counts and percentages
                                 readable without hovering. -->
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[30rem] text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                            <th scope="col" class="py-2 pr-3 text-left font-semibold">Problem</th>
                                            <th scope="col" class="px-3 py-2 text-right font-semibold">Count</th>
                                            <th scope="col" class="px-3 py-2 text-right font-semibold">%</th>
                                            <th scope="col" class="py-2 pl-3 text-right font-semibold">Kumulatif</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in payload.pareto.categories"
                                            :key="row.category_id"
                                            class="border-b border-line/60 last:border-0"
                                        >
                                            <td class="py-2 pr-3">
                                                <span class="text-ink">{{ row.category }}</span>
                                                <span
                                                    v-if="row.is_vital_few"
                                                    class="ml-2 rounded bg-info/12 px-1.5 py-0.5 text-[0.6rem] font-semibold tracking-wide text-info uppercase whitespace-nowrap"
                                                >Vital few</span>
                                            </td>
                                            <td class="px-3 py-2 text-right text-ink tabular-nums">{{ row.count }}</td>
                                            <td class="px-3 py-2 text-right text-ink-muted tabular-nums">
                                                {{ row.percentage.toFixed(1) }}%
                                            </td>
                                            <td class="py-2 pl-3 text-right font-medium text-ink tabular-nums">
                                                {{ row.cumulative_percentage.toFixed(1) }}%
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </PanelCard>

                    <PanelCard
                        title="Critical Material"
                        subtitle="Material dengan risiko tertinggi pada periode ini"
                        :loading="loading"
                        :empty="payload.critical_materials.length === 0"
                        empty-message="Tidak ada material yang berstatus critical pada periode ini."
                        @retry="refresh"
                    >
                        <ul class="divide-y divide-line/60">
                            <li
                                v-for="material in payload.critical_materials.slice(0, 7)"
                                :key="material.material_id"
                                class="flex items-start justify-between gap-3 px-5 py-3.5"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ material.material_name }}</p>
                                    <p class="text-xs text-ink-subtle">
                                        {{ material.material_code }} · {{ material.category }}
                                    </p>
                                    <p class="mt-1 text-xs leading-relaxed text-ink-muted">
                                        {{ material.reasons.join(' · ') }}
                                    </p>
                                </div>
                                <StatusBadge
                                    class="shrink-0"
                                    :label="material.risk_label"
                                    :variant="material.risk_variant"
                                />
                            </li>
                        </ul>
                    </PanelCard>
                </div>

                <!-- PO monitoring -->
                <PanelCard
                    title="Detail Monitoring PO Delivery"
                    subtitle="Baris yang perlu perhatian ditampilkan lebih dahulu"
                    :loading="loading"
                    :empty="payload.recent_deliveries.length === 0"
                    empty-message="Tidak ada baris PO pada periode ini."
                    @retry="refresh"
                >
                    <PoMonitoringTable :rows="payload.recent_deliveries" />
                </PanelCard>

                <!-- Definitions -->
                <section class="card p-5">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Definisi &amp; Rumus</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-3">
                        <div
                            v-for="definition in payload.definitions"
                            :key="definition.title"
                            class="rounded-lg border border-line bg-canvas/40 p-4"
                        >
                            <dt class="flex items-center gap-2 text-sm font-semibold text-ink">
                                <AppIcon name="info" :size="14" class="text-info" />
                                {{ definition.title }}
                            </dt>
                            <dd class="mt-1.5 text-xs leading-relaxed text-ink-muted">
                                {{ definition.description }}
                            </dd>
                            <dd class="mt-2 rounded bg-surface-raised px-2.5 py-1.5 font-mono text-[0.7rem] text-ink">
                                {{ definition.formula }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </template>
        </div>
    </AppLayout>
</template>