<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{ meta: Paginated<unknown> }>();

const number = new Intl.NumberFormat('id-ID');

/**
 * Laravel writes its previous/next labels as HTML entities. Decoding the two
 * we actually use keeps them readable without v-html, which was the only
 * unescaped sink in the application - harmless while the labels come from the
 * framework, and stored XSS the day one carries data.
 */
const ENTITIES: Record<string, string> = {
    '&laquo;': '\u00ab',
    '&raquo;': '\u00bb',
    '&amp;': '&',
    '&hellip;': '\u2026',
};

function label(raw: string): string {
    return Object.entries(ENTITIES).reduce(
        (text, [entity, character]) => text.split(entity).join(character),
        raw,
    );
}
</script>

<template>
    <nav
        v-if="meta.last_page > 1 || meta.total > 0"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3"
        :aria-label="t('common.pagination_nav')"
    >
        <p class="text-xs text-ink-muted">{{ t('common.showing') }}<span class="font-medium text-ink">{{ number.format(meta.from ?? 0) }}</span>
            –
            <span class="font-medium text-ink">{{ number.format(meta.to ?? 0) }}</span>
            dari
            <span class="font-medium text-ink">{{ number.format(meta.total) }}</span>
            data
        </p>

        <div v-if="meta.last_page > 1" class="flex flex-wrap items-center gap-1">
            <template v-for="(link, index) in meta.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="min-w-[2rem] rounded-md px-2.5 py-1.5 text-center text-xs font-medium transition"
                    :class="
                        link.active
                            ? 'bg-brand text-white'
                            : 'text-ink-muted hover:bg-surface-hover hover:text-ink'
                    "
                >{{ label(link.label) }}</Link>
                <span
                    v-else
                    class="min-w-[2rem] px-2.5 py-1.5 text-center text-xs text-ink-subtle/50"
                >{{ label(link.label) }}</span>
            </template>
        </div>
    </nav>
</template>
