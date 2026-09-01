<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Paginated, ProblemQueueSummary, ProblemSummary, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    records: Paginated<ProblemSummary>;
    filters: {
        search?: string;
        status?: string;
        severity?: string;
        supplier_id?: number;
        problem_category_id?: number;
        overdue?: boolean | string;
    };
    options: {
        statuses: SelectOption[];
        severities: SelectOption[];
        categories: SelectOption[];
        suppliers: SelectOption[];
    };
    summary: ProblemQueueSummary;
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const severity = ref(props.filters.severity ?? '');
const supplierId = ref(props.filters.supplier_id ?? '');
const categoryId = ref(props.filters.problem_category_id ?? '');
const overdueOnly = ref(Boolean(props.filters.overdue));

const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

function formatDate(value: string | null): string {
    return value ? dateFmt.format(new Date(value)) : '—';
}

let timer: ReturnType<typeof setTimeout> | undefined;

watch([search, status, severity, supplierId, categoryId, overdueOnly], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('problems.index'),
            {
                search: search.value || undefined,
                status: status.value || undefined,
                severity: severity.value || undefined,
                supplier_id: supplierId.value || undefined,
                problem_category_id: categoryId.value || undefined,
                overdue: overdueOnly.value ? 1 : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head title="Problem Analysis" />

    <AppLayout
        current="problems"
        title="Problem Analysis"
        subtitle="Masalah delivery, root cause, dan corrective action"
    >
        <div class="space-y-5">
            <!-- Counted in the database, not derived from the page of rows below. -->
            <div class="grid gap-4 sm:grid-cols-3">
                <article class="card p-4">
                    <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">Problem terbuka</p>
                    <p class="mt-1 text-2xl font-semibold text-ink tabular-nums">{{ summary.open }}</p>
                </article>
                <article class="card p-4">
                    <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">Melewati due date</p>
                    <p
                        class="mt-1 text-2xl font-semibold tabular-nums"
                        :class="summary.overdue > 0 ? 'text-critical' : 'text-ink'"
                    >
                        {{ summary.overdue }}
                    </p>
                </article>
                <article class="card p-4">
                    <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">Severity critical</p>
                    <p
                        class="mt-1 text-2xl font-semibold tabular-nums"
                        :class="summary.critical > 0 ? 'text-critical' : 'text-ink'"
                    >
                        {{ summary.critical }}
                    </p>
                </article>
            </div>

            <section class="card">
                <header class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                    <div class="relative min-w-[11rem] flex-1 sm:max-w-xs">
                        <input
                            v-model="search"
                            type="search"
                            class="field-input pl-9"
                            placeholder="Cari no. problem atau deskripsi…"
                            aria-label="Cari problem"
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

                    <select v-model="severity" class="field-input w-auto min-w-[9rem]" aria-label="Filter severity">
                        <option value="">Semua severity</option>
                        <option v-for="option in options.severities" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <select v-model="categoryId" class="field-input w-auto min-w-[10rem]" :aria-label="t('filter.category')">
                        <option value="">{{ t('filter.all_categories') }}</option>
                        <option v-for="option in options.categories" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <select v-model="supplierId" class="field-input w-auto min-w-[11rem]" :aria-label="t('filter.supplier')">
                        <option value="">{{ t('filter.all_suppliers') }}</option>
                        <option v-for="option in options.suppliers" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <label class="flex items-center gap-2 text-sm text-ink-muted">
                        <input v-model="overdueOnly" type="checkbox" class="size-4 rounded border-line" />
                        Terlambat saja
                    </label>
                </header>

                <EmptyState
                    v-if="records.data.length === 0"
                    :title="t('msg.no_problem')"
                    message="Problem dilaporkan dari halaman delivery yang bersangkutan."
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[62rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">No Problem</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.date') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.supplier') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.category') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.severity') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.target') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Tindakan</th>
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
                                <td class="px-5 py-3">
                                    <span class="font-medium whitespace-nowrap text-ink">{{ row.problem_number }}</span>
                                    <span v-if="row.delivery_number" class="block text-xs text-ink-subtle">
                                        {{ row.delivery_number }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-ink-muted tabular-nums">
                                    {{ formatDate(row.problem_date) }}
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ row.supplier_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-ink-muted">{{ row.category_name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="row.severity_label" :variant="row.severity_variant" />
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap tabular-nums">
                                    <span :class="row.is_overdue ? 'font-semibold text-critical' : 'text-ink-muted'">
                                        {{ formatDate(row.due_date) }}
                                    </span>
                                    <span v-if="row.is_overdue" class="ml-1.5 inline-flex items-center gap-1 text-xs text-critical">
                                        <AppIcon name="warning" :size="12" />{{ t('state.late') }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-ink tabular-nums">
                                    {{ row.corrective_actions_count }}
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="row.status_label" :variant="row.status_variant" />
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <Link
                                        :href="route('problems.show', row.ulid)"
                                        class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-info"
                                    >{{ t('common.details') }}</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :meta="records" />
            </section>
        </div>
    </AppLayout>
</template>
