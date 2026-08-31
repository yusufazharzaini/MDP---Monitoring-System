import type { route as routeFn } from 'ziggy-js';

declare global {
    /** Ziggy's helper, injected by the @routes directive. */
    const route: typeof routeFn;

    interface Window {
        route: typeof routeFn;
    }
}

/**
 * The ZiggyVue plugin installs route() as a global component property, so it is
 * callable from any template. Declaring it here is what lets vue-tsc see that -
 * without it every `:href="route(...)"` looks like a missing property.
 */
declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof routeFn;
    }
}

export {};
