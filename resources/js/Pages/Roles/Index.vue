<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PermissionGroup, RoleRow } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    roles: RoleRow[];
    groups: PermissionGroup[];
    can: { update: boolean };
}>();

const selected = ref<RoleRow>(props.roles[0]);
const form = useForm<{ permissions: string[] }>({ permissions: [...props.roles[0].permissions] });

function select(role: RoleRow): void {
    selected.value = role;
    form.defaults({ permissions: [...role.permissions] });
    form.reset();
    form.permissions = [...role.permissions];
}

function toggleGroup(group: PermissionGroup): void {
    const names = group.permissions.map((permission) => permission.name);
    const allOn = names.every((name) => form.permissions.includes(name));

    form.permissions = allOn
        ? form.permissions.filter((name) => !names.includes(name))
        : [...new Set([...form.permissions, ...names])];
}

function groupState(group: PermissionGroup): 'all' | 'some' | 'none' {
    const on = group.permissions.filter((permission) => form.permissions.includes(permission.name)).length;

    if (on === 0) return 'none';

    return on === group.permissions.length ? 'all' : 'some';
}

function save(): void {
    form.put(route('roles.update', selected.value.id), { preserveScroll: true });
}

const editable = computed(() => props.can.update && !selected.value.protected);
const dirty = computed(
    () =>
        [...form.permissions].sort().join('|') !== [...selected.value.permissions].sort().join('|'),
);
</script>

<template>
    <Head title="Peran & Permission" />

    <AppLayout
        current="roles"
        title="Peran & Permission"
        subtitle="Apa yang boleh dilakukan setiap peran"
    >
        <div class="grid gap-5 lg:grid-cols-[16rem_1fr]">
            <section class="card overflow-hidden">
                <header class="border-b border-line px-4 py-3">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('common.role') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Peran adalah jabatan organisasi &mdash; ditetapkan bersama sistem.
                    </p>
                </header>

                <ul>
                    <li v-for="role in roles" :key="role.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 border-b border-line/60 px-4 py-3 text-left text-sm transition last:border-0"
                            :class="role.id === selected.id ? 'bg-brand/10 text-ink' : 'text-ink-muted hover:bg-surface-hover'"
                            :aria-current="role.id === selected.id ? 'true' : undefined"
                            @click="select(role)"
                        >
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ role.name }}</span>
                                <span class="text-xs text-ink-subtle">
                                    {{ role.users_count }} pengguna &middot; {{ role.permissions.length }} permission
                                </span>
                            </span>
                            <AppIcon v-if="role.protected" name="warning" :size="14" class="shrink-0 text-warning" />
                        </button>
                    </li>
                </ul>
            </section>

            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ selected.name }}</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            {{ form.permissions.length }} permission dipilih
                        </p>
                    </div>

                    <button
                        v-if="editable"
                        type="button"
                        class="rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-50"
                        :disabled="form.processing || !dirty"
                        @click="save"
                    >
                        {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
                    </button>
                </header>

                <p
                    v-if="selected.protected"
                    class="border-b border-line bg-warning-ground px-5 py-3 text-sm text-warning"
                    role="status"
                >
                    <AppIcon name="warning" :size="14" class="mr-1 inline" />
                    Peran ini tidak dapat diubah. Peran inilah jalan masuk terakhir bila konfigurasi
                    peran lain keliru.
                </p>

                <div class="space-y-4 p-5">
                    <fieldset
                        v-for="group in groups"
                        :key="group.group"
                        class="rounded-lg border border-line p-4"
                        :disabled="!editable"
                    >
                        <legend class="flex items-center gap-2 px-1">
                            <button
                                type="button"
                                class="text-xs font-semibold tracking-wider text-ink uppercase transition"
                                :class="editable ? 'hover:text-info' : 'cursor-not-allowed'"
                                :disabled="!editable"
                                @click="toggleGroup(group)"
                            >
                                {{ group.group }}
                            </button>
                            <span
                                class="rounded px-1.5 py-0.5 text-[0.6rem] font-semibold tracking-wide uppercase"
                                :class="{
                                    'bg-good-ground text-success': groupState(group) === 'all',
                                    'bg-warning-ground text-warning': groupState(group) === 'some',
                                    'bg-surface-hover text-ink-subtle': groupState(group) === 'none',
                                }"
                            >
                                {{ groupState(group) === 'all' ? 'penuh' : groupState(group) === 'some' ? 'sebagian' : 'tidak ada' }}
                            </span>
                        </legend>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                class="flex items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-sm transition"
                                :class="[
                                    form.permissions.includes(permission.name)
                                        ? 'bg-brand/10 text-ink ring-1 ring-brand/40'
                                        : 'text-ink-muted',
                                    editable ? 'cursor-pointer hover:bg-surface-hover' : 'cursor-not-allowed opacity-70',
                                ]"
                            >
                                <input
                                    v-model="form.permissions"
                                    type="checkbox"
                                    class="size-4 rounded border-line"
                                    :value="permission.name"
                                    :disabled="!editable"
                                />
                                {{ permission.action }}
                            </label>
                        </div>
                    </fieldset>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
