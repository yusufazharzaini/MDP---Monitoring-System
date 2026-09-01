<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { NotificationRow, Paginated } from '@/Types';

defineProps<{
    records: Paginated<NotificationRow>;
    unread: number;
}>();

function markRead(notification: NotificationRow): void {
    router.post(route('notifications.read', notification.id), {}, { preserveScroll: true });
}

function markAllRead(): void {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}

/** Opening a notification is also reading it. */
function open(notification: NotificationRow): void {
    if (!notification.read) {
        router.post(
            route('notifications.read', notification.id),
            {},
            { preserveScroll: true, onSuccess: () => router.visit(notification.url as string) },
        );
        return;
    }

    router.visit(notification.url as string);
}
</script>

<template>
    <Head title="Notifikasi" />

    <AppLayout current="notifications" title="Notifikasi" subtitle="Yang perlu perhatian Anda">
        <section class="card">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <p class="text-sm text-ink-muted">
                    <span class="font-semibold text-ink">{{ unread }}</span> belum dibaca
                </p>

                <button
                    v-if="unread > 0"
                    type="button"
                    class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                    @click="markAllRead"
                >
                    Tandai semua sudah dibaca
                </button>
            </header>

            <EmptyState
                v-if="records.data.length === 0"
                title="Tidak ada notifikasi"
                message="Pemberitahuan tentang purchase order yang menunggu persetujuan dan problem yang melewati target akan muncul di sini."
            />

            <ul v-else class="divide-y divide-line/60">
                <li
                    v-for="row in records.data"
                    :key="row.id"
                    class="flex flex-wrap items-start justify-between gap-3 px-5 py-4 transition"
                    :class="row.read ? '' : 'bg-brand/5'"
                >
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full"
                            :class="row.read ? 'bg-transparent' : 'bg-brand'"
                            :aria-label="row.read ? undefined : 'Belum dibaca'"
                        />
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                                {{ row.title }}
                                <StatusBadge :label="row.severity" :variant="row.severity" />
                            </p>
                            <p class="mt-0.5 text-sm leading-relaxed text-ink-muted">{{ row.message }}</p>
                            <p class="mt-1 text-xs text-ink-subtle">{{ row.created_at }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            v-if="row.url"
                            type="button"
                            class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-ink-muted transition hover:border-info hover:text-info"
                            @click="open(row)"
                        >
                            Buka
                        </button>
                        <button
                            v-if="!row.read"
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-semibold text-ink-subtle transition hover:text-ink"
                            @click="markRead(row)"
                        >
                            Tandai dibaca
                        </button>
                    </div>
                </li>
            </ul>

            <Pagination :meta="records" />
        </section>
    </AppLayout>
</template>
