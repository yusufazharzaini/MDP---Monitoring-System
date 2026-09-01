<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import ToggleInput from '@/Components/Form/ToggleInput.vue';
import type { BadgeVariant } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

interface Contact {
    id: number;
    name: string;
    position: string | null;
    phone: string | null;
    email: string | null;
    is_primary: boolean;
    status: string;
    status_label: string;
    status_variant: BadgeVariant;
}

interface SupplierRecord {
    id: number;
    ulid: string;
    code: string;
    name: string;
    status_label: string;
    status_variant: BadgeVariant;
    [key: string]: string | number | boolean | null;
}

const props = defineProps<{
    record: SupplierRecord;
    contacts: Contact[];
    can: { create: boolean; update: boolean; delete: boolean };
}>();

/** Contacts are maintained here because a contact has no life of its own. */
const editing = ref<Contact | null>(null);
const showForm = ref(false);
const pendingDelete = ref<Contact | null>(null);

const form = useForm({
    supplier_id: props.record.id,
    name: '',
    position: '',
    phone: '',
    email: '',
    is_primary: false,
    status: 'ACTIVE',
});

const statusOptions = [
    { value: 'ACTIVE', label: 'Active' },
    { value: 'INACTIVE', label: 'Inactive' },
];

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.supplier_id = props.record.id;
    showForm.value = true;
}

function openEdit(contact: Contact): void {
    editing.value = contact;
    form.clearErrors();
    form.name = contact.name;
    form.position = contact.position ?? '';
    form.phone = contact.phone ?? '';
    form.email = contact.email ?? '';
    form.is_primary = contact.is_primary;
    form.status = contact.status;
    showForm.value = true;
}

function submit(): void {
    const done = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(route('supplier-contacts.update', [props.record.ulid, editing.value.id]), done);
    } else {
        form.post(route('supplier-contacts.store', props.record.ulid), done);
    }
}

function destroy(): void {
    if (!pendingDelete.value) {
        return;
    }

    router.delete(route('supplier-contacts.destroy', [props.record.ulid, pendingDelete.value.id]), {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}

const details: Array<{ label: string; key: string }> = [
    { label: 'Kode', key: 'code' },
    { label: 'Nama', key: 'name' },
    { label: 'Nama singkat', key: 'short_name' },
    { label: 'Tipe', key: 'supplier_type_label' },
    { label: 'Kota', key: 'city' },
    { label: 'Negara', key: 'country' },
    { label: 'Lead time (hari)', key: 'lead_time_days' },
    { label: 'Payment term', key: 'payment_term' },
    { label: 'PIC', key: 'pic_name' },
    { label: 'Email PIC', key: 'pic_email' },
    { label: 'Telepon PIC', key: 'pic_phone' },
    { label: 'Alamat', key: 'address' },
];
</script>

<template>
    <Head :title="`Supplier ${record.code}`" />

    <AppLayout
        current="suppliers"
        :title="record.name"
        :subtitle="`Kode ${record.code}`"
    >
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('suppliers.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >
                    &larr; Kembali ke daftar supplier
                </Link>
                <Link
                    v-if="can.update"
                    :href="route('suppliers.edit', record.ulid)"
                    class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                >{{ t('supplier.edit') }}</Link>
            </div>

            <section class="card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('supplier.detail') }}</h2>
                    <StatusBadge :label="record.status_label" :variant="record.status_variant" />
                </div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="detail in details" :key="detail.key">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            {{ detail.label }}
                        </dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ record[detail.key] || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('supplier.contacts') }}</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">{{ t('supplier.primary_hint') }}</p>
                    </div>
                    <button
                        v-if="can.create"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft"
                        @click="openCreate"
                    >
                        <AppIcon name="supplier" :size="15" />{{ t('supplier.add_contact') }}</button>
                </header>

                <EmptyState
                    v-if="contacts.length === 0"
                    :title="t('supplier.no_contacts')"
                    message="Tambahkan minimal satu kontak agar tim purchasing tahu harus menghubungi siapa."
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[42rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.name') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('supplier.position') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.phone') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.email') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="contact in contacts"
                                :key="contact.id"
                                class="border-b border-line/60 transition last:border-0 hover:bg-surface-hover"
                            >
                                <td class="px-5 py-3">
                                    <span class="text-ink">{{ contact.name }}</span>
                                    <span
                                        v-if="contact.is_primary"
                                        class="ml-2 rounded bg-info/12 px-1.5 py-0.5 text-[0.6rem] font-semibold tracking-wide text-info uppercase"
                                    >{{ t('supplier.primary') }}</span>
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ contact.position || '—' }}</td>
                                <td class="px-5 py-3 text-ink-muted">{{ contact.phone || '—' }}</td>
                                <td class="px-5 py-3 text-ink-muted">{{ contact.email || '—' }}</td>
                                <td class="px-5 py-3">
                                    <StatusBadge :label="contact.status_label" :variant="contact.status_variant" />
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="can.update"
                                            type="button"
                                            class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-info"
                                            @click="openEdit(contact)"
                                        >{{ t('common.edit') }}</button>
                                        <button
                                            v-if="can.delete"
                                            type="button"
                                            class="rounded-md px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-critical"
                                            @click="pendingDelete = contact"
                                        >{{ t('common.delete') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Contact form -->
        <Teleport to="body">
            <div
                v-if="showForm"
                class="fixed inset-0 z-50 flex items-center justify-center bg-canvas/80 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="showForm = false"
            >
                <form class="card w-full max-w-lg p-6" @submit.prevent="submit">
                    <h2 class="text-base font-semibold text-ink">
                        {{ editing ? 'Ubah kontak' : 'Tambah kontak' }}
                    </h2>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <TextInput id="contact-name" v-model="form.name" :label="t('common.name')" required :error="form.errors.name" />
                        <TextInput id="contact-position" v-model="form.position" :label="t('supplier.position')" :error="form.errors.position" />
                        <TextInput id="contact-phone" v-model="form.phone" :label="t('common.phone')" :error="form.errors.phone" />
                        <TextInput id="contact-email" v-model="form.email" type="email" :label="t('common.email')" :error="form.errors.email" />
                        <SelectInput id="contact-status" v-model="form.status" :label="t('common.status')" required :options="statusOptions" :error="form.errors.status" />
                        <div class="sm:col-span-2">
                            <ToggleInput
                                id="contact-primary"
                                v-model="form.is_primary"
                                :label="t('supplier.make_primary')"
                                hint="Menandai kontak ini akan melepas tanda utama dari kontak lain."
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                            @click="showForm = false"
                        >{{ t('common.cancel') }}</button>
                        <button
                            type="submit"
                            class="rounded-lg bg-brand px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <ConfirmDialog
            :open="pendingDelete !== null"
            :title="t('supplier.delete_contact_confirm')"
            :message="`Kontak ${pendingDelete?.name ?? ''} akan dihapus dari supplier ini.`"
            @confirm="destroy"
            @cancel="pendingDelete = null"
        />
    </AppLayout>
</template>
