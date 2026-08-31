import { computed, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useDashboardFilterStore } from '@/Stores/dashboardFilter';
import type { DashboardPayload } from '@/Types';

const DEBOUNCE_MS = 350;

/**
 * Owns the dashboard's data lifecycle: watching the filter store, debouncing
 * the refetch, and exposing the loading / error flags every panel reads.
 *
 * It fetches JSON rather than making a full Inertia visit, so changing a filter
 * repaints the panels without re-rendering the shell or losing scroll position.
 * All figures arrive already computed - nothing here does arithmetic on them.
 */
export function useDashboard(initial: DashboardPayload, initialGeneratedAt: string) {
    const store = useDashboardFilterStore();
    store.hydrate(initial.filters);

    const payload = ref<DashboardPayload>(initial);
    const generatedAt = ref<string>(initialGeneratedAt);
    const loading = ref(false);
    const error = ref<string | null>(null);

    let timer: ReturnType<typeof setTimeout> | undefined;
    let inFlight: AbortController | undefined;

    async function fetchData(): Promise<void> {
        // A newer request always wins: without this, a slow response for an old
        // filter can land after a fast one and repaint stale numbers.
        inFlight?.abort();
        const controller = new AbortController();
        inFlight = controller;

        loading.value = true;
        error.value = null;

        try {
            const query = new URLSearchParams(store.queryParams).toString();

            const response = await fetch(`${route('dashboard.data')}?${query}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
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
            if (!controller.signal.aborted) {
                loading.value = false;
            }
        }
    }

    function refresh(): void {
        clearTimeout(timer);
        void fetchData();
    }

    // Debounced so stepping through a long supplier list fires one request at
    // rest rather than one per keystroke.
    watch(
        () => store.queryParams,
        () => {
            clearTimeout(timer);
            timer = setTimeout(() => void fetchData(), DEBOUNCE_MS);
        },
        { deep: true },
    );

    const { hasActiveFilters, activeFilterCount } = storeToRefs(store);

    return {
        payload,
        generatedAt,
        loading,
        error,
        filters: store,
        hasActiveFilters: computed(() => hasActiveFilters.value),
        activeFilterCount: computed(() => activeFilterCount.value),
        refresh,
        resetFilters: () => store.reset(),
    };
}
