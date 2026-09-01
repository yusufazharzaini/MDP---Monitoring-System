<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { AuditLogRow, Paginated, SelectOption } from '@/Types';

const props = defineProps<{
    records: Paginated<AuditLogRow>;
    filters: {
        module?: string;
        action?: string;
        user_id?: number;
        date_from?: string;
        date_to?: string;
        record_id?: number;
    };
    options: { actions: SelectOption[]; modules: SelectOption[]; users: SelectOption[] };
}>();

const module = ref(props.filters.module ?? '');
const action = ref(props.filters.action ?? '');
const userId = ref(props.filters.user_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let timer: ReturnType<typeof setTimeout> | undefined;

watch([module, action, userId, dateFrom, dateTo], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('audit-logs.index'),
            {
                module: module.value || undefined,
                action: action.value || undefined,
                user_id: userId.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head title="Audit Log" />

    <AppLayout
        current="audit-logs"
        title="Audit Log"
        subtitle="Jejak aktivitas sistem — hanya dapat dibaca"
    >
        <section class="card">
            <header class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                <select v-model="module" class="field-input w-auto min-w-[10rem]" aria-label="Filter modul">
                    <option value="">Semua modul</option>
                    <option v-for="option in options.modules" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <select v-model="action" class="field-input w-auto min-w-[9rem]" aria-label="Filter aksi">
                    <option value="">Semua aksi</option>
                    <option v-for="option in options.actions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <select v-model="userId" class="field-input w-auto min-w-[11rem]" aria-label="Filter pengguna">
                    <option value="">Semua pengguna</option>
                    <option v-for="option in options.users" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <label class="flex items-center gap-2 text-sm text-ink-muted">
                    Dari
                    <input v-model="dateFrom" type="date" class="field-input w-auto" aria-label="Tanggal awal" />
                </label>
                <label class="flex items-center gap-2 text-sm text-ink-muted">
                    Sampai
                    <input v-model="dateTo" type="date" class="field-input w-auto" aria-label="Tanggal akhir" />
                </label>
            </header>

            <EmptyState
                v-if="records.data.length === 0"
                title="Tidak ada aktivitas"
                message="Tidak ada catatan yang cocok dengan filter ini."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[58rem] text-sm">
                    <thead>
                        <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Waktu</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Pengguna</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Aksi</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Modul</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Perubahan</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in records.data"
                            :key="row.id"
                            class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                        >
                            <td class="px-5 py-3 whitespace-nowrap text-ink-muted tabular-nums">
                                {{ row.created_at }}
                            </td>
                            <td class="px-5 py-3 text-ink">{{ row.user_name }}</td>
                            <td class="px-5 py-3">
                                <StatusBadge :label="row.action_label" :variant="row.action_variant" />
                            </td>
                            <td class="px-5 py-3 text-ink-muted">
                                {{ row.module }}
                                <span v-if="row.record_id" class="text-xs text-ink-subtle">#{{ row.record_id }}</span>
                            </td>
                            <td class="max-w-md px-5 py-3">
                                <span v-if="row.changes.length === 0" class="text-xs text-ink-subtle">—</span>
                                <ul v-else class="space-y-1 text-xs">
                                    <li v-for="change in row.changes" :key="change.field" class="text-ink-muted">
                                        <span class="font-medium text-ink">{{ change.field }}</span>

                                        <!-- A list field: what moved, not both whole lists. -->
                                        <span
                                            v-if="change.added.length || change.removed.length"
                                            class="ml-1 inline-flex flex-wrap gap-1 align-top"
                                        >
                                            <span
                                                v-for="name in change.added"
                                                :key="`+${name}`"
                                                class="rounded bg-good-ground px-1.5 py-0.5 text-success"
                                            >
                                                + {{ name }}
                                            </span>
                                            <span
                                                v-for="name in change.removed"
                                                :key="`-${name}`"
                                                class="rounded bg-critical-ground px-1.5 py-0.5 text-critical"
                                            >
                                                &minus; {{ name }}
                                            </span>
                                        </span>

                                        <template v-else>
                                            <span v-if="change.from !== null" class="text-critical break-all">
                                                {{ change.from }}
                                            </span>
                                            <span v-if="change.from !== null && change.to !== null"> &rarr;</span>
                                            <span v-if="change.to !== null" class="text-success break-all">
                                                {{ change.to }}
                                            </span>
                                        </template>
                                    </li>
                                </ul>
                            </td>
                            <td class="px-5 py-3 text-xs text-ink-subtle tabular-nums">{{ row.ip_address ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="records" />
        </section>
    </AppLayout>
</template>
