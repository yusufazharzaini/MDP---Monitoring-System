import { computed, reactive, ref, watch } from 'vue';
import type { DashboardFilters, DashboardPayload } from '@/types';

/** Filter fields the user can change from the filter bar. */
export type EditableFilters = Pick<
    DashboardFilters,
    'period' | 'plant_id' | 'supplier_id' | 'material_id' | 'material_category_id'
>;

const DEBOUNCE_MS = 350;

/**
 * Owns the dashboard's data lifecycle: the filter state, the debounced refetch,
 * and the loading / error flags every panel reads.
 *
 * It fetches JSON rather than making a full Inertia visit, so changing a filter
 * repaints the panels without re-rendering the shell or losing scroll position.
 * All figures arrive already computed - this composable never does arithmetic
 * on them.
 */
export function useDashboard(initial: DashboardPayload, initialGeneratedAt: string) {
    const payload = ref<DashboardPayload>(initial);
    const generatedAt = ref<string>(initialGeneratedAt);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const filters = reactive<EditableFilters>({
        period: initial.filters.period,
        plant_id: initial.filters.plant_id,
        supplier_id: initial.filters.supplier_id,
        material_id: initial.filters.material_id,
        material_category_id: initial.filters.material_category_id,
    });

    let timer: ReturnType<typeof setTimeout> | undefined;
    let inFlight: AbortController | undefined;

    function queryString(): string {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries(filters)) {
            if (value !== null && value !== '') {
                params.set(key, String(value));
            }
        }

        return params.toString();
    }

    async function fetchData(): Promise<void> {
        // A newer request always wins: without this, a slow response for an old
        // filter can land after a fast one and repaint stale numbers.
        inFlight?.abort();
        inFlight = new AbortController();

        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(`${route('dashboard.data')}?${queryString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: inFlight.signal,
            });

            if (!response.ok) {
                throw new Error(
                    response.status === 422
                        ? 'Filter tidak valid. Periksa kembali pilihan Anda.'
                        : `Server merespons ${response.status}.`,
                );
            }

            const body = (await response.json()) as { dashboard: DashboardPayload; generatedAt: string };

            payload.value = body.dashboard;
            generatedAt.value = body.generatedAt;
        } catch (caught) {
            // An aborted request is a superseded one, not a failure to report.
            if (caught instanceof DOMException && caught.name === 'AbortError') {
                return;
            }

            error.value = caught instanceof Error ? caught.message : 'Gagal memuat data dashboard.';
        } finally {
            if (!inFlight?.signal.aborted) {
                loading.value = false;
            }
        }
    }

    function refresh(): void {
        clearTimeout(timer);
        void fetchData();
    }

    function resetFilters(): void {
        filters.plant_id = null;
        filters.supplier_id = null;
        filters.material_id = null;
        filters.material_category_id = null;
    }

    // Debounced so dragging through a long supplier list fires one request, not one per keystroke.
    watch(
        () => ({ ...filters }),
        () => {
            clearTimeout(timer);
            timer = setTimeout(() => void fetchData(), DEBOUNCE_MS);
        },
        { deep: true },
    );

    const hasActiveFilters = computed(
        () =>
            filters.plant_id !== null ||
            filters.supplier_id !== null ||
            filters.material_id !== null ||
            filters.material_category_id !== null,
    );

    return { payload, generatedAt, loading, error, filters, refresh, resetFilters, hasActiveFilters };
}
