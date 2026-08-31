<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import type { CorrectiveActionRow, ProblemAttachmentRow, ProblemRecord } from '@/Types';

const props = defineProps<{
    record: ProblemRecord;
    can: {
        update: boolean;
        close: boolean;
        cancel: boolean;
        addAction: boolean;
        addAttachment: boolean;
    };
    /** Answered by the backend: the closing rule is never re-derived here. */
    closable: boolean;
    maxAttachmentKb: number;
}>();

const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

function formatDate(value: string | null): string {
    return value ? dateFmt.format(new Date(value)) : '—';
}

const details = computed(() => [
    { label: 'Tanggal problem', value: formatDate(props.record.problem_date) },
    { label: 'Delivery', value: props.record.delivery_number ?? '—' },
    { label: 'Purchase order', value: props.record.po_number ?? '—' },
    { label: 'Supplier', value: props.record.supplier_name ?? '—' },
    { label: 'Plant', value: props.record.plant_name ?? '—' },
    { label: 'Material', value: props.record.material_name ?? 'Tidak spesifik' },
    { label: 'Kategori', value: props.record.category_name ?? '—' },
    { label: 'PIC', value: props.record.pic || '—' },
    { label: 'Dilaporkan oleh', value: props.record.reported_by ?? '—' },
    { label: 'Target penyelesaian', value: formatDate(props.record.due_date) },
]);

/* --- Corrective actions -------------------------------------------------- */

const actionForm = useForm({
    action_date: new Date().toISOString().slice(0, 10),
    description: '',
    due_date: '',
});

function addAction(): void {
    actionForm
        .transform((data) => ({ ...data, due_date: data.due_date || null }))
        .post(route('corrective-actions.store', props.record.ulid), {
            preserveScroll: true,
            onSuccess: () => actionForm.reset(),
        });
}

function startAction(action: CorrectiveActionRow): void {
    router.post(
        route('corrective-actions.start', [props.record.ulid, action.id]),
        {},
        { preserveScroll: true },
    );
}

function completeAction(action: CorrectiveActionRow): void {
    router.post(
        route('corrective-actions.complete', [props.record.ulid, action.id]),
        {},
        { preserveScroll: true },
    );
}

function removeAction(action: CorrectiveActionRow): void {
    router.delete(route('corrective-actions.destroy', [props.record.ulid, action.id]), {
        preserveScroll: true,
    });
}

/* --- Attachments --------------------------------------------------------- */

const uploadForm = useForm<{ file: File | null }>({ file: null });
const fileInput = ref<HTMLInputElement | null>(null);

function upload(): void {
    uploadForm.post(route('problem-attachments.store', props.record.ulid), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function removeAttachment(attachment: ProblemAttachmentRow): void {
    router.delete(route('problem-attachments.destroy', [props.record.ulid, attachment.ulid]), {
        preserveScroll: true,
    });
}

const maxAttachmentMb = computed(() => (props.maxAttachmentKb / 1024).toFixed(1));

/* --- Closing and cancelling ---------------------------------------------- */

const closing = ref(false);
const cancelling = ref(false);
const closeForm = useForm({ note: '' });
const cancelForm = useForm({ reason: '' });

function close(): void {
    closeForm
        .transform((data) => ({ ...data, note: data.note || null }))
        .post(route('problems.close', props.record.ulid), {
            preserveScroll: true,
            onSuccess: () => {
                closing.value = false;
                closeForm.reset();
            },
        });
}

function cancel(): void {
    cancelForm.post(route('problems.cancel', props.record.ulid), {
        preserveScroll: true,
        onSuccess: () => {
            cancelling.value = false;
            cancelForm.reset();
        },
    });
}

const isSettled = computed(() => !['OPEN', 'IN_PROGRESS'].includes(props.record.status));
</script>

<template>
    <Head :title="record.problem_number" />

    <AppLayout
        current="problems"
        :title="record.problem_number"
        :subtitle="`${record.supplier_name ?? ''} · ${record.category_name ?? ''}`"
    >
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('problems.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >
                    &larr; Kembali ke daftar problem
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="record.delivery_ulid"
                        :href="route('deliveries.show', record.delivery_ulid)"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                    >
                        Lihat delivery
                    </Link>
                    <Link
                        v-if="can.update"
                        :href="route('problems.edit', record.ulid)"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                    >
                        Ubah
                    </Link>
                    <button
                        v-if="can.close"
                        type="button"
                        class="rounded-lg px-3.5 py-2 text-sm font-semibold transition"
                        :class="
                            closable
                                ? 'bg-brand text-white hover:bg-brand-soft'
                                : 'cursor-not-allowed border border-line text-ink-subtle'
                        "
                        :disabled="!closable"
                        :title="
                            closable
                                ? undefined
                                : 'Butuh minimal satu corrective action berstatus Done sebelum problem dapat ditutup.'
                        "
                        @click="closing = true"
                    >
                        Tutup problem
                    </button>
                    <button
                        v-if="can.cancel"
                        type="button"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-critical hover:text-critical"
                        @click="cancelling = true"
                    >
                        Batalkan
                    </button>
                </div>
            </div>

            <div
                v-if="record.is_overdue"
                class="rounded-lg bg-critical-ground px-4 py-3 text-sm text-critical ring-1 ring-critical/30"
                role="status"
            >
                <AppIcon name="warning" :size="14" class="mr-1 inline" />
                Problem ini melewati target penyelesaian {{ formatDate(record.due_date) }}.
            </div>

            <div
                v-else-if="record.status === 'CLOSED'"
                class="rounded-lg bg-good-ground px-4 py-3 text-sm text-success ring-1 ring-success/30"
                role="status"
            >
                Problem ditutup pada {{ record.closed_at }} setelah corrective action diselesaikan.
            </div>

            <div
                v-else-if="record.status === 'CANCELLED'"
                class="rounded-lg bg-surface-hover px-4 py-3 text-sm text-ink-muted ring-1 ring-line"
                role="status"
            >
                Problem ini dibatalkan dan tidak lagi dihitung pada analisis Pareto maupun penilaian supplier.
            </div>

            <section class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Informasi Problem</h2>
                    <div class="flex items-center gap-2">
                        <StatusBadge :label="record.severity_label" :variant="record.severity_variant" />
                        <StatusBadge :label="record.status_label" :variant="record.status_variant" />
                    </div>
                </div>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div v-for="detail in details" :key="detail.label">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            {{ detail.label }}
                        </dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ detail.value }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-5">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">Deskripsi</dt>
                        <dd class="mt-0.5 text-sm leading-relaxed text-ink-muted">{{ record.description }}</dd>
                    </div>
                    <div v-if="record.root_cause" class="sm:col-span-2 lg:col-span-5">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">Root Cause</dt>
                        <dd class="mt-0.5 text-sm leading-relaxed text-ink-muted">{{ record.root_cause }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Corrective Action</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            Problem hanya dapat ditutup bila minimal satu tindakan berstatus Done.
                        </p>
                    </div>
                    <StatusBadge
                        :label="closable ? 'Siap ditutup' : 'Belum ada tindakan selesai'"
                        :variant="closable ? 'success' : 'warning'"
                    />
                </header>

                <EmptyState
                    v-if="record.corrective_actions.length === 0"
                    title="Belum ada corrective action"
                    message="Catat tindakan yang sudah dilakukan terhadap problem ini."
                />

                <ul v-else class="divide-y divide-line/60">
                    <li v-for="action in record.corrective_actions" :key="action.id" class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-relaxed text-ink">{{ action.description }}</p>
                                <p class="mt-1 text-xs text-ink-subtle">
                                    {{ formatDate(action.action_date) }}
                                    <span v-if="action.action_by"> · {{ action.action_by }}</span>
                                    <span v-if="action.due_date">
                                        · target
                                        <span :class="action.is_overdue ? 'font-semibold text-critical' : ''">
                                            {{ formatDate(action.due_date) }}
                                        </span>
                                    </span>
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <StatusBadge :label="action.status_label" :variant="action.status_variant" />
                                <template v-if="can.addAction && !action.is_done">
                                    <button
                                        v-if="action.status === 'OPEN'"
                                        type="button"
                                        class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-ink-muted transition hover:text-ink"
                                        @click="startAction(action)"
                                    >
                                        Mulai
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-ink-muted transition hover:border-success hover:text-success"
                                        @click="completeAction(action)"
                                    >
                                        Selesai
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-ink-muted transition hover:border-critical hover:text-critical"
                                        @click="removeAction(action)"
                                    >
                                        Hapus
                                    </button>
                                </template>
                            </div>
                        </div>
                    </li>
                </ul>

                <form v-if="can.addAction && !isSettled" class="border-t border-line px-5 py-4" @submit.prevent="addAction">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <TextInput
                            id="action_date"
                            v-model="actionForm.action_date"
                            type="date"
                            label="Tanggal Tindakan"
                            required
                            :error="actionForm.errors.action_date"
                        />
                        <TextInput
                            id="action_due_date"
                            v-model="actionForm.due_date"
                            type="date"
                            label="Target Selesai"
                            :error="actionForm.errors.due_date"
                        />
                        <div class="sm:col-span-2">
                            <TextareaInput
                                id="action_description"
                                v-model="actionForm.description"
                                label="Tindakan yang Dilakukan"
                                required
                                :error="actionForm.errors.description"
                            />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="submit"
                            class="rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-60"
                            :disabled="actionForm.processing"
                        >
                            Tambah corrective action
                        </button>
                    </div>
                </form>
            </section>

            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Lampiran</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            Disimpan pada penyimpanan privat. Maksimal {{ maxAttachmentMb }} MB per file.
                        </p>
                    </div>
                </header>

                <EmptyState
                    v-if="record.attachments.length === 0"
                    title="Belum ada lampiran"
                    message="Unggah foto barang, berita acara, atau dokumen pendukung lain."
                />

                <ul v-else class="divide-y divide-line/60">
                    <li
                        v-for="file in record.attachments"
                        :key="file.ulid"
                        class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-hover text-ink-muted">
                                <AppIcon :name="file.is_image ? 'material' : 'report'" :size="16" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">{{ file.file_name }}</p>
                                <p class="text-xs text-ink-subtle">
                                    {{ file.human_file_size }}
                                    <span v-if="file.uploaded_by"> · {{ file.uploaded_by }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <!--
                                A plain link, not an Inertia visit: the response
                                is a file stream rather than a page.
                            -->
                            <a
                                :href="route('problem-attachments.download', [record.ulid, file.ulid])"
                                class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-ink-muted transition hover:border-info hover:text-info"
                            >
                                Unduh
                            </a>
                            <button
                                v-if="can.addAttachment && !isSettled"
                                type="button"
                                class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-ink-muted transition hover:border-critical hover:text-critical"
                                @click="removeAttachment(file)"
                            >
                                Hapus
                            </button>
                        </div>
                    </li>
                </ul>

                <form v-if="can.addAttachment" class="border-t border-line px-5 py-4" @submit.prevent="upload">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[16rem] flex-1">
                            <label for="attachment" class="field-label">Pilih file</label>
                            <input
                                id="attachment"
                                ref="fileInput"
                                type="file"
                                class="field-input file:mr-3 file:rounded-md file:border-0 file:bg-surface-hover file:px-3 file:py-1.5 file:text-sm file:text-ink-muted"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.doc,.docx"
                                @change="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-critical">
                                {{ uploadForm.errors.file }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-60"
                            :disabled="uploadForm.processing || !uploadForm.file"
                        >
                            Unggah
                        </button>
                    </div>
                    <p v-if="uploadForm.progress" class="mt-2 text-xs text-ink-subtle">
                        Mengunggah {{ uploadForm.progress.percentage }}%
                    </p>
                </form>
            </section>
        </div>

        <ConfirmDialog
            :open="closing"
            title="Tutup problem"
            message="Problem akan ditandai selesai. Catatan penutup akan menggantikan root cause bila diisi."
            confirm-label="Tutup problem"
            :processing="closeForm.processing"
            @cancel="closing = false"
            @confirm="close"
        >
            <TextareaInput
                id="close_note"
                v-model="closeForm.note"
                label="Catatan penutup"
                :error="closeForm.errors.note"
            />
        </ConfirmDialog>

        <ConfirmDialog
            :open="cancelling"
            title="Batalkan problem"
            message="Problem tetap tercatat tetapi tidak lagi dihitung pada analisis. Sebutkan alasannya."
            confirm-label="Batalkan problem"
            :processing="cancelForm.processing"
            @cancel="cancelling = false"
            @confirm="cancel"
        >
            <TextareaInput
                id="cancel_reason"
                v-model="cancelForm.reason"
                label="Alasan pembatalan"
                required
                :error="cancelForm.errors.reason"
            />
        </ConfirmDialog>
    </AppLayout>
</template>
