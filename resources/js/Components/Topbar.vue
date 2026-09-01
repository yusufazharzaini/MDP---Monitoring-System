<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import type { SharedPageProps } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        generatedAt: string | null;
        refreshing?: boolean;
        /** Only screens that can actually refetch show the refresh control. */
        refreshable?: boolean;
    }>(),
    { refreshable: false },
);

defineEmits<{ toggleSidebar: []; refresh: [] }>();

const page = usePage<SharedPageProps>();
const user = computed(() => page.props.auth.user);
const unread = computed(() => page.props.unreadNotifications ?? 0);

/**
 * "Last update" is the moment the server computed the payload, not the moment
 * the browser rendered it - with a cached payload those differ, and the
 * server's time is the honest one.
 */
const lastUpdated = computed(() => {
    if (!props.generatedAt) {
        return null;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(props.generatedAt));
});

function logout(): void {
    router.post(route('logout'));
}
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur">
        <div class="flex flex-wrap items-center gap-3 px-4 py-3.5 sm:px-6">
            <button
                type="button"
                class="rounded-lg border border-line p-2 text-ink-muted transition hover:text-ink lg:hidden"
                aria-label="Buka menu"
                @click="$emit('toggleSidebar')"
            >
                <AppIcon name="menu" :size="18" />
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold text-ink sm:text-lg">{{ title }}</h1>
                <p v-if="subtitle" class="truncate text-xs text-ink-muted">{{ subtitle }}</p>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <p
                    v-if="refreshable && lastUpdated"
                    class="hidden items-center gap-1.5 text-xs text-ink-subtle md:flex"
                    :title="`Data dihitung pada ${lastUpdated}`"
                >
                    <AppIcon name="clock" :size="13" />
                    <span class="tabular-nums">{{ lastUpdated }}</span>
                </p>

                <button
                    v-if="refreshable"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink-muted transition hover:border-info hover:text-info disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="refreshing"
                    @click="$emit('refresh')"
                >
                    <AppIcon name="refresh" :size="14" :class="refreshing ? 'animate-spin' : ''" />
                    <span class="hidden sm:inline">{{ refreshing ? 'Memuat…' : 'Refresh' }}</span>
                </button>

                <!-- The bell. Count comes from a shared prop, so every page has it. -->
                <Link
                    :href="route('notifications.index')"
                    class="relative rounded-lg border border-line p-2 text-ink-muted transition hover:border-info hover:text-info"
                    :aria-label="unread > 0 ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'"
                >
                    <AppIcon name="warning" :size="15" />
                    <span
                        v-if="unread > 0"
                        class="absolute -top-1.5 -right-1.5 min-w-[1.1rem] rounded-full bg-critical px-1 text-[0.6rem] font-bold text-white tabular-nums"
                    >
                        {{ unread > 99 ? '99+' : unread }}
                    </span>
                </Link>

                <div class="hidden items-center gap-3 border-l border-line pl-3 sm:flex">
                    <div class="text-right">
                        <p class="text-xs font-medium text-ink">{{ user?.name }}</p>
                        <p class="text-[0.65rem] text-ink-subtle">{{ user?.roles.join(', ') }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-line p-2 text-ink-muted transition hover:border-critical hover:text-critical"
                        :aria-label="t('auth.sign_out')"
                        @click="logout"
                    >
                        <AppIcon name="logout" :size="15" />
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>
