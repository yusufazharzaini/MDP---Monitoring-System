<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import type { DashboardFilterOptions, SelectOption } from '@/Types';
import { useDashboardFilterStore, type IdFilterKey } from '@/Stores/dashboardFilter';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{
    options: DashboardFilterOptions;
    hasActiveFilters: boolean;
    loading?: boolean;
}>();

defineEmits<{ reset: [] }>();

/** The store is the single owner of the selection; this bar just edits it. */
const filters = useDashboardFilterStore();

/** Selects bind to strings; the query layer wants an id or nothing. */
function toId(value: string): number | null {
    return value === '' ? null : Number(value);
}

/** Only the id-valued filters are rendered as selects; period has its own input. */
const selects: Array<{ key: IdFilterKey; label: string; source: keyof DashboardFilterOptions; all: string }> = [
    { key: 'plant_id', label: 'Plant', source: 'plants', all: 'Semua Plant' },
    { key: 'supplier_id', label: 'Supplier', source: 'suppliers', all: 'Semua Supplier' },
    { key: 'material_id', label: 'Material', source: 'materials', all: 'Semua Material' },
    { key: 'material_category_id', label: 'Kategori', source: 'materialCategories', all: 'Semua Kategori' },
];

function optionsFor(options: DashboardFilterOptions, key: keyof DashboardFilterOptions): SelectOption[] {
    return options[key] ?? [];
}
</script>

<template>
    <!-- One row above the panels: every filter in the same place, nothing hidden in a drawer. -->
    <section class="card p-4" :aria-label="t('filter.dashboard')">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[9rem] flex-1 sm:max-w-[11rem]">
                <label for="filter-period" class="field-label">{{ t('common.period') }}</label>
                <input
                    id="filter-period"
                    v-model="filters.period"
                    type="month"
                    class="field-input"
                    :disabled="loading"
                />
            </div>

            <div v-for="select in selects" :key="select.key" class="min-w-[10rem] flex-1 sm:max-w-[14rem]">
                <label :for="`filter-${select.key}`" class="field-label">{{ select.label }}</label>
                <select
                    :id="`filter-${select.key}`"
                    class="field-input"
                    :disabled="loading"
                    :value="filters[select.key] ?? ''"
                    @change="filters[select.key] = toId(($event.target as HTMLSelectElement).value)"
                >
                    <option value="">{{ select.all }}</option>
                    <option
                        v-for="option in optionsFor(options, select.source)"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <button
                v-if="hasActiveFilters"
                type="button"
                class="inline-flex h-[2.35rem] items-center gap-2 rounded-lg border border-line px-3 text-xs font-semibold text-ink-muted transition hover:border-critical hover:text-critical"
                @click="$emit('reset')"
            >
                <AppIcon name="close" :size="13" />{{ t('filter.reset') }}</button>

            <p
                v-if="loading"
                class="inline-flex h-[2.35rem] items-center gap-2 text-xs font-medium text-ink-subtle"
                role="status"
            >
                <span class="size-3 animate-spin rounded-full border-2 border-ink-subtle/40 border-t-ink-muted" />
                Memuat data…
            </p>
        </div>
    </section>
</template>
