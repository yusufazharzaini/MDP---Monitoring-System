<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { useTranslate } from '@/Composables/useTranslate';
import type { SharedPageProps } from '@/Types';

const page = usePage<SharedPageProps>();
const { t } = useTranslate();

const current = computed<string>(() => page.props.locale.current);
const options = computed(() => page.props.locale.supported);

/**
 * A full visit rather than a client-side swap: the server renders enum labels,
 * validation messages and notifications, so the page has to come back from it
 * to be fully translated. preserveScroll keeps the reader where they were.
 */
function change(event: Event): void {
    const locale = (event.target as HTMLSelectElement).value;

    if (locale === current.value) {
        return;
    }

    router.post(route('locale.update'), { locale }, { preserveScroll: true });
}
</script>

<template>
    <label class="flex items-center gap-2">
        <span class="sr-only">{{ t('common.language') }}</span>
        <select
            :value="current"
            class="rounded-md border border-line bg-surface px-2 py-1 text-xs text-ink transition hover:border-brand focus:border-brand focus:outline-none"
            :aria-label="t('common.language')"
            @change="change"
        >
            <option v-for="option in options" :key="option.code" :value="option.code">
                {{ option.native }}
            </option>
        </select>
    </label>
</template>
