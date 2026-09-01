<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ServiceRateChart from '@/Components/Charts/ServiceRateChart.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { DashboardFilters, SupplierScorecard } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    scorecard: SupplierScorecard;
    filters: DashboardFilters;
    evaluations: Array<{
        id: number;
        period: string;
        total_score: number;
        grade_label: string;
        grade_variant: 'success' | 'danger' | 'warning' | 'info' | 'neutral';
        status_label: string;
        status_variant: 'success' | 'danger' | 'warning' | 'info' | 'neutral';
        approved_by: string | null;
    }>;
    can: { viewEvaluations: boolean };
}>();

const number = new Intl.NumberFormat('id-ID');
const rate = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

/** Every figure here is computed by DashboardService; the page only formats. */
const counts = computed(() => [
    { label: 'Total delivery', value: number.format(props.scorecard.metrics.total_delivery) },
    { label: 'Tepat waktu', value: number.format(props.scorecard.metrics.on_time_delivery) },
    { label: 'Terlambat', value: number.format(props.scorecard.metrics.late_delivery), tone: 'warning' },
    { label: 'Quantity kurang', value: number.format(props.scorecard.metrics.short_delivery), tone: 'serious' },
    { label: 'Pemenuhan quantity', value: `${rate.format(props.scorecard.metrics.quantity_fulfillment)}%` },
]);

const problemTotal = computed(() =>
    props.scorecard.problem_breakdown.reduce((sum, row) => sum + row.count, 0),
);
</script>

<template>
    <Head :title="scorecard.supplier.name" />

    <AppLayout
        current="supplier-performance"
        :title="scorecard.supplier.name"
        :subtitle="`${scorecard.supplier.code} · lead time ${scorecard.supplier.lead_time_days} hari`"
    >
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('supplier-performance.index', { period: filters.period })"
                    class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >
                    &larr; Kembali ke peringkat supplier
                </Link>
                <Link
                    :href="route('suppliers.show', scorecard.supplier.ulid)"
                    class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                >
                    Data supplier
                </Link>
            </div>

            <section class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            Service rate {{ filters.period }}
                        </p>
                        <p class="mt-1 flex items-baseline gap-3">
                            <span class="text-4xl font-semibold text-ink tabular-nums">
                                {{ rate.format(scorecard.service_rate) }}%
                            </span>
                            <StatusBadge :label="scorecard.grade_label" :variant="scorecard.grade_variant" />
                        </p>
                        <p class="mt-1.5 text-sm" :class="scorecard.meets_target ? 'text-success' : 'text-critical'">
                            {{ scorecard.meets_target ? 'Memenuhi' : 'Di bawah' }} target
                            {{ rate.format(scorecard.service_rate_target) }}%
                        </p>
                    </div>

                    <dl class="grid flex-1 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        <div v-for="item in counts" :key="item.label">
                            <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                                {{ item.label }}
                            </dt>
                            <dd
                                class="mt-0.5 text-lg font-semibold tabular-nums"
                                :class="{
                                    'text-warning': item.tone === 'warning' && item.value !== '0',
                                    'text-serious': item.tone === 'serious' && item.value !== '0',
                                    'text-ink': !item.tone || item.value === '0',
                                }"
                            >
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-3">
                <section class="card lg:col-span-2">
                    <header class="border-b border-line px-5 py-4">
                        <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Tren Service Rate</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            Enam bulan terakhir. Bulan tanpa penerimaan tidak digambar sebagai 0%.
                        </p>
                    </header>
                    <div class="p-4">
                        <ServiceRateChart :points="scorecard.trend" :target="scorecard.service_rate_target" />
                    </div>
                </section>

                <section class="card">
                    <header class="border-b border-line px-5 py-4">
                        <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Problem per Kategori</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">{{ problemTotal }} problem pada periode ini</p>
                    </header>

                    <EmptyState
                        v-if="scorecard.problem_breakdown.length === 0"
                        :title="t('msg.no_problem')"
                        message="Tidak ada masalah delivery yang tercatat untuk supplier ini pada periode terpilih."
                    />

                    <ul v-else class="divide-y divide-line/60">
                        <li
                            v-for="row in scorecard.problem_breakdown"
                            :key="row.category"
                            class="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                        >
                            <span class="text-ink-muted">{{ row.category }}</span>
                            <span class="font-semibold text-ink tabular-nums">{{ row.count }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <section v-if="can.viewEvaluations" class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Riwayat Evaluasi Bulanan</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Skor yang sudah disetujui bersifat beku &mdash; koreksi data setelahnya tidak mengubahnya.
                    </p>
                </header>

                <EmptyState
                    v-if="evaluations.length === 0"
                    :title="t('msg.no_evaluation')"
                    message="Evaluasi bulanan dihitung dari halaman Evaluasi Supplier."
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.period') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Total Skor</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.grade') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.approved_by') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in evaluations"
                                :key="row.id"
                                class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                            >
                                <td class="px-5 py-3 font-medium text-ink tabular-nums">{{ row.period }}</td>
                                <td class="px-5 py-3 text-right text-ink tabular-nums">
                                    {{ rate.format(row.total_score) }}
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="row.grade_label" :variant="row.grade_variant" />
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="row.status_label" :variant="row.status_variant" />
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ row.approved_by ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
