<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps<{ open: boolean; current: string }>();
defineEmits<{ close: [] }>();

interface NavItem {
    key: string;
    label: string;
    icon: string;
    href?: string;
}

/**
 * Modules without a screen yet are rendered disabled rather than hidden, so the
 * shape of the system is visible while it is being built.
 */
const items: NavItem[] = [
    { key: 'dashboard', label: 'Overview', icon: 'dashboard', href: route('dashboard') },
    { key: 'suppliers', label: 'Supplier', icon: 'supplier', href: route('suppliers.index') },
    { key: 'materials', label: 'Material', icon: 'material', href: route('materials.index') },
    { key: 'plants', label: 'Plant', icon: 'box', href: route('plants.index') },
    { key: 'warehouses', label: 'Warehouse', icon: 'box', href: route('warehouses.index') },
    { key: 'departments', label: 'Department', icon: 'report', href: route('departments.index') },
    { key: 'purchase-orders', label: 'Purchase Order', icon: 'order', href: route('purchase-orders.index') },
    { key: 'deliveries', label: 'Delivery', icon: 'delivery', href: route('deliveries.index') },
    { key: 'problems', label: 'Problem Analysis', icon: 'problem', href: route('problems.index') },
    { key: 'reports', label: 'Report', icon: 'report' },
];
</script>

<template>
    <!-- Backdrop, mobile only -->
    <div
        v-if="open"
        class="fixed inset-0 z-30 bg-canvas/80 backdrop-blur-sm lg:hidden"
        @click="$emit('close')"
    />

    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-line bg-surface transition-transform duration-200 lg:static lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex items-center justify-between border-b border-line px-5 py-5">
            <div>
                <p class="text-lg font-bold tracking-[0.25em] text-brand">TORICA</p>
                <p class="mt-0.5 text-[0.65rem] tracking-wide text-ink-subtle uppercase">Dashboard</p>
            </div>
            <button
                type="button"
                class="text-ink-muted transition hover:text-ink lg:hidden"
                aria-label="Tutup menu"
                @click="$emit('close')"
            >
                <AppIcon name="close" :size="18" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Navigasi utama">
            <template v-for="item in items" :key="item.key">
                <Link
                    v-if="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                    :class="
                        current === item.key
                            ? 'bg-brand/15 text-ink ring-1 ring-brand/40'
                            : 'text-ink-muted hover:bg-surface-hover hover:text-ink'
                    "
                    :aria-current="current === item.key ? 'page' : undefined"
                >
                    <AppIcon :name="item.icon" :size="17" />
                    {{ item.label }}
                </Link>

                <span
                    v-else
                    class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-subtle/70"
                    :title="`${item.label} - belum tersedia`"
                >
                    <AppIcon :name="item.icon" :size="17" />
                    {{ item.label }}
                    <span class="ml-auto text-[0.6rem] tracking-wide uppercase">Soon</span>
                </span>
            </template>
        </nav>

        <div class="border-t border-line px-5 py-4">
            <p class="text-[0.65rem] leading-relaxed text-ink-subtle">
                Material Delivery Performance<br />Monitoring System
            </p>
        </div>
    </aside>
</template>
