<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/Types';

defineProps<{ meta: Paginated<unknown> }>();

const number = new Intl.NumberFormat('id-ID');
</script>

<template>
    <nav
        v-if="meta.last_page > 1 || meta.total > 0"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3"
        aria-label="Navigasi halaman"
    >
        <p class="text-xs text-ink-muted">
            Menampilkan
            <span class="font-medium text-ink">{{ number.format(meta.from ?? 0) }}</span>
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
                    v-html="link.label"
                />
                <span
                    v-else
                    class="min-w-[2rem] px-2.5 py-1.5 text-center text-xs text-ink-subtle/50"
                    v-html="link.label"
                />
            </template>
        </div>
    </nav>
</template>
