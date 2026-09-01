<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Paginated, SelectOption, UserRow } from '@/Types';

const props = defineProps<{
    records: Paginated<UserRow>;
    filters: { search?: string; role?: string; status?: string; department_id?: number; trashed?: boolean | string };
    options: { roles: SelectOption[]; statuses: SelectOption[]; departments: SelectOption[] };
    can: { create: boolean };
    superAdminCount: number;
}>();

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status ?? '');
const departmentId = ref(props.filters.department_id ?? '');
const trashed = ref(Boolean(props.filters.trashed));

const retiring = ref<UserRow | null>(null);

function retire(): void {
    if (!retiring.value) return;

    router.delete(route('users.destroy', retiring.value.ulid), {
        preserveScroll: true,
        onSuccess: () => (retiring.value = null),
    });
}

function restore(user: UserRow): void {
    router.post(route('users.restore', user.ulid), {}, { preserveScroll: true });
}

let timer: ReturnType<typeof setTimeout> | undefined;

watch([search, role, status, departmentId, trashed], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('users.index'),
            {
                search: search.value || undefined,
                role: role.value || undefined,
                status: status.value || undefined,
                department_id: departmentId.value || undefined,
                trashed: trashed.value ? 1 : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head title="Pengguna" />

    <AppLayout current="users" title="Pengguna" subtitle="Akun, peran, dan akses sistem">
        <section class="card">
            <header class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                <div class="relative min-w-[11rem] flex-1 sm:max-w-xs">
                    <input
                        v-model="search"
                        type="search"
                        class="field-input pl-9"
                        placeholder="Cari nama, email, atau NIK…"
                        aria-label="Cari pengguna"
                    />
                    <AppIcon
                        name="filter"
                        :size="14"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-subtle"
                    />
                </div>

                <select v-model="role" class="field-input w-auto min-w-[9rem]" aria-label="Filter peran">
                    <option value="">Semua peran</option>
                    <option v-for="option in options.roles" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <select v-model="status" class="field-input w-auto min-w-[9rem]" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option v-for="option in options.statuses" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <select v-model="departmentId" class="field-input w-auto min-w-[10rem]" aria-label="Filter departemen">
                    <option value="">Semua departemen</option>
                    <option v-for="option in options.departments" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <label class="flex items-center gap-2 text-sm text-ink-muted">
                    <input v-model="trashed" type="checkbox" class="size-4 rounded border-line" />
                    Akun dicabut
                </label>

                <Link
                    v-if="can.create"
                    :href="route('users.create')"
                    class="ml-auto rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft"
                >
                    Tambah pengguna
                </Link>
            </header>

            <p
                v-if="superAdminCount <= 1"
                class="border-b border-line bg-warning-ground px-5 py-3 text-sm text-warning"
                role="status"
            >
                <AppIcon name="warning" :size="14" class="mr-1 inline" />
                Hanya ada {{ superAdminCount }} super administrator aktif. Akun itu tidak dapat
                dinonaktifkan atau diturunkan perannya sampai ada penggantinya.
            </p>

            <EmptyState
                v-if="records.data.length === 0"
                title="Tidak ada pengguna"
                message="Tidak ada akun yang cocok dengan filter ini."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[58rem] text-sm">
                    <thead>
                        <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Nama</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Jabatan</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Departemen</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Peran</th>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in records.data"
                            :key="row.id"
                            class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                        >
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">
                                    {{ row.name }}
                                    <span v-if="row.is_self" class="ml-1 text-xs font-normal text-ink-subtle">(Anda)</span>
                                </p>
                                <p class="text-xs text-ink-subtle">{{ row.email }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-muted">{{ row.position ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-muted">{{ row.department_name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="flex flex-wrap gap-1">
                                    <span
                                        v-for="name in row.roles"
                                        :key="name"
                                        class="rounded-md bg-surface-hover px-2 py-0.5 text-xs text-ink-muted"
                                    >
                                        {{ name }}
                                    </span>
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <StatusBadge
                                    :label="row.is_retired ? 'Dicabut' : row.status_label"
                                    :variant="row.is_retired ? 'danger' : row.status_variant"
                                />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="row.can.update && !row.is_retired"
                                        :href="route('users.edit', row.ulid)"
                                        class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-info"
                                    >
                                        Ubah
                                    </Link>
                                    <button
                                        v-if="row.can.delete"
                                        type="button"
                                        class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-critical"
                                        @click="retiring = row"
                                    >
                                        Cabut akses
                                    </button>
                                    <button
                                        v-if="row.can.restore"
                                        type="button"
                                        class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-success"
                                        @click="restore(row)"
                                    >
                                        Pulihkan
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :meta="records" />
        </section>

        <ConfirmDialog
            :open="retiring !== null"
            title="Cabut akses pengguna"
            :message="`Akun ${retiring?.name ?? ''} tidak lagi dapat masuk. Riwayatnya tetap tersimpan dan akses dapat dipulihkan kemudian.`"
            confirm-label="Cabut akses"
            @cancel="retiring = null"
            @confirm="retire"
        />
    </AppLayout>
</template>
