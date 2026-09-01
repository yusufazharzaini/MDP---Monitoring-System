<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { EvaluationSummary, Paginated, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    records: Paginated<EvaluationSummary>;
    filters: { period?: string; status?: string; grade?: string; supplier_id?: number };
    options: {
        statuses: SelectOption[];
        grades: SelectOption[];
        suppliers: SelectOption[];
        latestPeriod: string;
    };
    can: { create: boolean };
}>();

const period = ref(props.filters.period ?? '');
const status = ref(props.filters.status ?? '');
const grade = ref(props.filters.grade ?? '');
const supplierId = ref(props.filters.supplier_id ?? '');

const score = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

const generating = ref(false);
const generateForm = useForm({ period: props.options.latestPeriod, supplier_id: '' as string | number });

function generate(): void {
    generateForm
        .transform((data) => ({ ...data, supplier_id: data.supplier_id || null }))
        .post(route('supplier-evaluations.store'), {
            preserveScroll: true,
            onSuccess: () => {
                generating.value = false;
                generateForm.reset();
            },
        });
}

let timer: ReturnType<typeof setTimeout> | undefined;

watch([period, status, grade, supplierId], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('supplier-evaluations.index'),
            {
                period: period.value || undefined,
                status: status.value || undefined,
                grade: grade.value || undefined,
                supplier_id: supplierId.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head :title="t('evaluation.title')" />

    <AppLayout
        current="supplier-evaluations"
        :title="t('evaluation.title')"
        subtitle="Scorecard bulanan: dihitung dari transaksi, dibekukan saat disetujui"
    >
        <section class="card">
            <header class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                <label class="flex items-center gap-2 text-sm text-ink-muted">{{ t('common.period') }}<input v-model="period" type="month" class="field-input w-auto" :aria-label="t('filter.period')" />
                </label>

                <select v-model="status" class="field-input w-auto min-w-[9rem]" :aria-label="t('filter.status')">
                    <option value="">{{ t('filter.all_status') }}</option>
                    <option v-for="option in options.statuses" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <select v-model="grade" class="field-input w-auto min-w-[9rem]" :aria-label="t('filter.grade')">
                    <option value="">{{ t('filter.all_grades') }}</option>
                    <option v-for="option in options.grades" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <select v-model="supplierId" class="field-input w-auto min-w-[11rem]" :aria-label="t('filter.supplier')">
                    <option value="">{{ t('filter.all_suppliers') }}</option>
                    <option v-for="option in options.suppliers" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <button
                    v-if="can.create"
                    type="button"
                    class="ml-auto rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft"
                    @click="generating = true"
                >{{ t('evaluation.calculate') }}</button>
            </header>

            <EmptyState
                v-if="records.data.length === 0"
                :title="t('msg.no_evaluation')"
                message="Hitung evaluasi untuk sebuah periode. Supplier tanpa penerimaan pada bulan itu dilewati."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[58rem] text-sm">
                    <thead>
                        <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.period') }}</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.supplier') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('entity.delivery') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('evaluation.quality') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('evaluation.quantity') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('evaluation.response') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('common.total') }}</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.grade') }}</th>
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
                            <td class="px-5 py-3 font-medium text-ink tabular-nums">{{ row.period }}</td>
                            <td class="px-5 py-3">
                                <p class="text-ink">{{ row.supplier_name }}</p>
                                <p class="text-xs text-ink-subtle">{{ row.supplier_code }}</p>
                            </td>
                            <td class="px-5 py-3 text-right text-ink-muted tabular-nums">{{ score.format(row.delivery_score) }}</td>
                            <td class="px-5 py-3 text-right text-ink-muted tabular-nums">{{ score.format(row.quality_score) }}</td>
                            <td class="px-5 py-3 text-right text-ink-muted tabular-nums">{{ score.format(row.quantity_score) }}</td>
                            <td class="px-5 py-3 text-right text-ink-muted tabular-nums">{{ score.format(row.responsiveness_score) }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-ink tabular-nums">{{ score.format(row.total_score) }}</td>
                            <td class="px-5 py-3">
                                <StatusBadge :label="row.grade_label" :variant="row.grade_variant" />
                            </td>
                            <td class="px-5 py-3">
                                <StatusBadge :label="row.status_label" :variant="row.status_variant" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <Link
                                    :href="route('supplier-evaluations.show', row.id)"
                                    class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-info"
                                >{{ t('common.details') }}</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="records" />
        </section>

        <Teleport to="body">
            <div
                v-if="generating"
                class="fixed inset-0 z-50 flex items-center justify-center bg-canvas/80 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                :aria-label="t('evaluation.calculate')"
                @click.self="generating = false"
            >
                <form class="card w-full max-w-md p-6" @submit.prevent="generate">
                    <h2 class="text-base font-semibold text-ink">{{ t('evaluation.calculate_monthly') }}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                        Skor dihitung ulang dari transaksi periode tersebut. Evaluasi yang sudah
                        disetujui dilewati, begitu pula supplier tanpa penerimaan.
                    </p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="gen-period" class="field-label">{{ t('common.period') }}</label>
                            <input
                                id="gen-period"
                                v-model="generateForm.period"
                                type="month"
                                class="field-input"
                                :max="options.latestPeriod"
                                required
                            />
                            <p v-if="generateForm.errors.period" class="mt-1 text-xs text-critical">
                                {{ generateForm.errors.period }}
                            </p>
                        </div>

                        <div>
                            <label for="gen-supplier" class="field-label">{{ t('entity.supplier') }}</label>
                            <select id="gen-supplier" v-model="generateForm.supplier_id" class="field-input">
                                <option value="">{{ t('evaluation.all_active_hint') }}</option>
                                <option v-for="option in options.suppliers" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                            @click="generating = false"
                        >{{ t('common.cancel') }}</button>
                        <button
                            type="submit"
                            class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-60"
                            :disabled="generateForm.processing"
                        >
                            {{ generateForm.processing ? 'Menghitung…' : 'Hitung' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
