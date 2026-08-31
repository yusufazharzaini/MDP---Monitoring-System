import { defineStore } from 'pinia';
import type { DashboardFilters } from '@/Types';

/** The filter fields a user can change from the filter bar. */
export interface DashboardFilterState {
    period: string;
    plant_id: number | null;
    supplier_id: number | null;
    material_id: number | null;
    material_category_id: number | null;
}

/** Keys that hold an id, as opposed to the period string. */
export type IdFilterKey = Exclude<keyof DashboardFilterState, 'period'>;

export const ID_FILTER_KEYS: IdFilterKey[] = [
    'plant_id',
    'supplier_id',
    'material_id',
    'material_category_id',
];

/**
 * The dashboard's filter selection, held in one place.
 *
 * It lives in a store rather than in the page because the same selection has to
 * survive navigation between dashboard screens - opening a supplier's scorecard
 * and coming back should not silently reset the period the user chose.
 *
 * The store holds the *selection* only. It performs no fetching and no
 * arithmetic; useDashboard turns a selection into a request, and the backend
 * turns a request into figures.
 */
export const useDashboardFilterStore = defineStore('dashboardFilter', {
    state: (): DashboardFilterState => ({
        period: '',
        plant_id: null,
        supplier_id: null,
        material_id: null,
        material_category_id: null,
    }),

    getters: {
        /** True when anything beyond the period narrows the view. */
        hasActiveFilters: (state): boolean =>
            ID_FILTER_KEYS.some((key) => state[key] !== null),

        /** How many scope filters are applied - drives the "reset" affordance. */
        activeFilterCount: (state): number =>
            ID_FILTER_KEYS.filter((key) => state[key] !== null).length,

        /** The selection as query parameters, with empty values left out. */
        queryParams: (state): Record<string, string> => {
            const params: Record<string, string> = {};

            for (const [key, value] of Object.entries(state)) {
                if (value !== null && value !== '') {
                    params[key] = String(value);
                }
            }

            return params;
        },
    },

    actions: {
        /**
         * Seed the store from what the server actually resolved, so the filter
         * bar shows the period the payload was computed for rather than a guess.
         */
        hydrate(filters: DashboardFilters): void {
            this.period = filters.period;
            this.plant_id = filters.plant_id;
            this.supplier_id = filters.supplier_id;
            this.material_id = filters.material_id;
            this.material_category_id = filters.material_category_id;
        },

        /** Clear the scope filters, keeping the chosen period. */
        reset(): void {
            for (const key of ID_FILTER_KEYS) {
                this[key] = null;
            }
        },
    },
});
