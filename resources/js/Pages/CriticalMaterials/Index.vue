<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { CriticalMaterialRow, DashboardFilters, SelectOption } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    filters: DashboardFilters;
    materials: CriticalMaterialRow[];
    summary: { total: number; high_risk: number; flagged: number };
    options: { plants: SelectOption[]; materialCategories: SelectOption[] };
}>();

const period = ref(props.filters.period);
const plantId = ref(props.filters.plant_id ?? '');
const categoryId = ref(props.filters.material_category_id ?? '');

const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

let timer: ReturnType<typeof setTimeout> | undefined;

watch([period, plantId, categoryId], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('critical-materials.index'),
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
    <Head :title="t('entity.critical_material')" />

    <AppLayout
        current="critical-materials"
        :title="t('entity.critical_material')"
        subtitle="Material yang memicu aturan kritis pada periode terpilih"
    >
        <div class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <article class="card p-4">
                    <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">Total critical</p>
                    <p class="mt-1 text-2xl font-semibold text-ink tabular-nums">{{ summary.total }}</p>
                </article>
                <article class="card p-4">
                    <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">Risiko tinggi</p>
                    <p
                        class="mt-1 text-2xl font-semibold tabular-nums"
                        :class="summary.high_risk > 0 ? 'text-critical' : 'text-ink'"
                    >
                        {{ summary.high_risk }}
                    </p>
                </article>
                <article class="card p-4">
                    <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                        Ditandai critical di master
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-ink tabular-nums">{{ summary.flagged }}</p>
                </article>
            </div>

            <section class="card">
                <header class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                    <label class="flex items-center gap-2 text-sm text-ink-muted">{{ t('common.period') }}<input v-model="period" type="month" class="field-input w-auto" :aria-label="t('common.period')" />
                    </label>

                    <select v-model="plantId" class="field-input w-auto min-w-[10rem]" :aria-label="t('filter.plant')">
                        <option value="">{{ t('filter.all_plants') }}</option>
                        <option v-for="option in options.plants" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <select v-model="categoryId" class="field-input w-auto min-w-[11rem]" :aria-label="t('filter.category')">
                        <option value="">{{ t('filter.all_categories') }}</option>
                        <option v-for="option in options.materialCategories" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </header>

                <EmptyState
                    v-if="materials.length === 0"
                    title="Tidak ada material critical"
                    message="Tidak ada material yang memicu aturan critical pada periode ini."
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[62rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.material') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.category') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('state.late') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('state.short') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Kekurangan Qty</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Problem Critical</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.reason') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">Risiko</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in materials"
                                :key="row.material_id"
                                class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                            >
                                <td class="px-5 py-3">
                                    <Link
                                        :href="route('materials.show', row.material_ulid)"
                                        class="font-medium text-ink transition hover:text-info"
                                    >
                                        {{ row.material_name }}
                                    </Link>
                                    <p class="flex items-center gap-1.5 text-xs text-ink-subtle">
                                        {{ row.material_code }}
                                        <span v-if="row.is_flagged_critical" class="inline-flex items-center gap-1 text-warning">
                                            <AppIcon name="warning" :size="11" />
                                            master critical
                                        </span>
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ row.category }}</td>
                                <td class="px-5 py-3 text-right tabular-nums" :class="row.late_count > 0 ? 'text-warning' : 'text-ink-muted'">
                                    {{ row.late_count }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums" :class="row.short_count > 0 ? 'text-serious' : 'text-ink-muted'">
                                    {{ row.short_count }}
                                </td>
                                <td class="px-5 py-3 text-right text-ink-muted tabular-nums">
                                    {{ number.format(row.shortage_quantity) }} {{ row.uom }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums" :class="row.critical_problem_count > 0 ? 'text-critical' : 'text-ink-muted'">
                                    {{ row.critical_problem_count }}
                                </td>
                                <td class="px-5 py-3">
                                    <ul class="space-y-0.5 text-xs text-ink-muted">
                                        <li v-for="reason in row.reasons" :key="reason">{{ reason }}</li>
                                    </ul>
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="row.risk_label" :variant="row.risk_variant" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
