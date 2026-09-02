import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { SharedPageProps, TranslationGroup } from '@/Types';

/**
 * Interface translation, reading the strings Inertia shares for the active
 * locale.
 *
 * There is no i18n library behind this on purpose. Laravel's lang files are
 * already the single source of truth - validation messages and notifications
 * resolve from them server-side - and a second dictionary in JavaScript would
 * be a second place for a translation to go stale.
 *
 * A missing key returns the key itself rather than an empty string: a screen
 * reading "nav.report" is an obvious bug, while a blank label looks like a
 * design decision and can survive review.
 */
export function useTranslate() {
    const page = usePage<SharedPageProps>();

    const locale = computed<string>(() => page.props.locale.current);

    function t(key: string, replacements: Record<string, string | number> = {}): string {
        const found = key
            .split('.')
            .reduce<string | TranslationGroup | undefined>(
                (carry, segment) =>
                    typeof carry === 'object' && carry !== null ? carry[segment] : undefined,
                page.props.translations,
            );

        if (typeof found !== 'string') {
            return key;
        }

        return Object.entries(replacements).reduce(
            (text, [token, value]) => text.replaceAll(`:${token}`, String(value)),
            found,
        );
    }

    return { t, locale };
}
