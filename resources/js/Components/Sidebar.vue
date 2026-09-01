<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { useTranslate } from '@/Composables/useTranslate';

defineProps<{ open: boolean; current: string }>();
defineEmits<{ close: [] }>();

const { t } = useTranslate();

interface NavItem {
    key: string;
    /** A key into lang/<locale>/ui.php, resolved at render time. */
    labelKey: string;
    icon: string;
    href?: string;
}

/**
 * Modules without a screen yet are rendered disabled rather than hidden, so the
 * shape of the system is visible while it is being built.
 */
const items: NavItem[] = [
    { key: 'dashboard', labelKey: 'nav.overview', icon: 'dashboard', href: route('dashboard') },
    { key: 'suppliers', labelKey: 'nav.supplier', icon: 'supplier', href: route('suppliers.index') },
    { key: 'materials', labelKey: 'nav.material', icon: 'material', href: route('materials.index') },
    { key: 'plants', labelKey: 'nav.plant', icon: 'box', href: route('plants.index') },
    { key: 'warehouses', labelKey: 'nav.warehouse', icon: 'box', href: route('warehouses.index') },
    { key: 'departments', labelKey: 'nav.department', icon: 'report', href: route('departments.index') },
    { key: 'purchase-orders', labelKey: 'nav.purchase_order', icon: 'order', href: route('purchase-orders.index') },
    { key: 'deliveries', labelKey: 'nav.delivery', icon: 'delivery', href: route('deliveries.index') },
    { key: 'problems', labelKey: 'nav.problem_analysis', icon: 'problem', href: route('problems.index') },
    { key: 'supplier-performance', labelKey: 'nav.supplier_performance', icon: 'trend', href: route('supplier-performance.index') },
    { key: 'supplier-evaluations', labelKey: 'nav.supplier_evaluation', icon: 'good', href: route('supplier-evaluations.index') },
    { key: 'critical-materials', labelKey: 'nav.critical_material', icon: 'warning', href: route('critical-materials.index') },
    { key: 'reports', labelKey: 'nav.report', icon: 'report', href: route('reports.index') },
    { key: 'users', labelKey: 'nav.user', icon: 'supplier', href: route('users.index') },
    { key: 'roles', labelKey: 'nav.role_permission', icon: 'settings', href: route('roles.index') },
    { key: 'audit-logs', labelKey: 'nav.audit_log', icon: 'clock', href: route('audit-logs.index') },
    { key: 'notifications', labelKey: 'nav.notification', icon: 'warning', href: route('notifications.index') },
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
                <p class="text-lg font-bold tracking-[0.25em] text-brand">YUSUF</p>
                <p class="mt-0.5 text-[0.65rem] tracking-wide text-ink-subtle uppercase">
                    {{ t('nav.dashboard') }}
                </p>
            </div>
            <button
                type="button"
                class="text-ink-muted transition hover:text-ink lg:hidden"
                :aria-label="t('common.cancel')"
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
                    {{ t(item.labelKey) }}
                </Link>

                <span
                    v-else
                    class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-subtle/70"
                    :title="`${t(item.labelKey)} - ${t('nav.soon')}`"
                >
                    <AppIcon :name="item.icon" :size="17" />
                    {{ t(item.labelKey) }}
                    <span class="ml-auto text-[0.6rem] tracking-wide uppercase">{{ t('nav.soon') }}</span>
                </span>
            </template>
        </nav>

        <div class="space-y-3 border-t border-line px-5 py-4">
            <LanguageSwitcher />
            <p class="text-[0.65rem] leading-relaxed text-ink-subtle">
                Material Delivery Performance<br />Monitoring System
            </p>
        </div>
    </aside>
</template>
