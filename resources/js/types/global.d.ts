import type { route as routeFn } from 'ziggy-js';

declare global {
    // Ziggy's helper, injected by the @routes directive.
    const route: typeof routeFn;

    interface Window {
        route: typeof routeFn;
    }
}

export {};
