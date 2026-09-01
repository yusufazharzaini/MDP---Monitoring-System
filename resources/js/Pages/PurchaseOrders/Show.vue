<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import type { PurchaseOrderRecord } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    record: PurchaseOrderRecord;
    can: { update: boolean; submit: boolean; approve: boolean; cancel: boolean };
}>();

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const confirming = ref<'submit' | 'approve' | null>(null);
const cancelling = ref(false);
const processing = ref(false);

const cancelForm = useForm({ reason: '' });

/** The lifecycle is a straight line; showing where the order sits on it is
 *  more useful than a badge alone. */
const stages = ['DRAFT', 'SUBMITTED', 'APPROVED', 'PARTIAL', 'COMPLETED'];
const currentStage = computed(() => stages.indexOf(props.record.status));
const isCancelled = computed(() => props.record.status === 'CANCELLED');

function act(action: 'submit' | 'approve'): void {
    processing.value = true;
    router.post(route(`purchase-orders.${action}`, props.record.ulid), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirming.value = null;
        },
    });
}

function cancel(): void {
    cancelForm.post(route('purchase-orders.cancel', props.record.ulid), {
        preserveScroll: true,
        onSuccess: () => {
            cancelling.value = false;
            cancelForm.reset();
        },
    });
}

const details = computed(() => [
    { label: 'Tanggal PO', value: dateFmt.format(new Date(props.record.po_date)) },
    { label: 'Supplier', value: `${props.record.supplier_code ?? ''} — ${props.record.supplier_name ?? ''}` },
    { label: 'Plant', value: props.record.plant_name ?? '—' },
    { label: 'Payment term', value: props.record.payment_term ?? '—' },
    { label: 'Dibuat oleh', value: props.record.created_by_name ?? '—' },
    {
        label: 'Disetujui oleh',
        value: props.record.approved_by_name
            ? `${props.record.approved_by_name} · ${dateFmt.format(new Date(props.record.approved_at as string))}`
            : '—',
    },
]);
</script>

<template>
    <Head :title="record.po_number" />

    <AppLayout
        current="purchase-orders"
        :title="record.po_number"
        :subtitle="`${record.supplier_name ?? ''} · ${record.plant_name ?? ''}`"
    >
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('purchase-orders.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >
                    &larr; Kembali ke daftar PO
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="can.update"
                        :href="route('purchase-orders.edit', record.ulid)"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                    >{{ t('common.edit') }}</Link>
                    <button
                        v-if="can.submit"
                        type="button"
                        class="rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft"
                        @click="confirming = 'submit'"
                    >
                        Ajukan approval
                    </button>
                    <button
                        v-if="can.approve"
                        type="button"
                        class="rounded-lg bg-good px-3.5 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                        @click="confirming = 'approve'"
                    >{{ t('common.approve') }}</button>
                    <Link
                        v-if="record.status === 'APPROVED' || record.status === 'PARTIAL'"
                        :href="route('deliveries.create', record.ulid)"
                        class="rounded-lg bg-good px-3.5 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                    >{{ t('action.receive_goods') }}</Link>
                    <button
                        v-if="can.cancel"
                        type="button"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-critical hover:text-critical"
                        @click="cancelling = true"
                    >{{ t('common.cancel_record') }}</button>
                </div>
            </div>

            <!-- Lifecycle -->
            <section class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <ol v-if="!isCancelled" class="flex flex-wrap items-center gap-2">
                        <li v-for="(stage, index) in stages" :key="stage" class="flex items-center gap-2">
                            <span
                                class="rounded-md px-2.5 py-1 text-[0.65rem] font-semibold tracking-wide uppercase"
                                :class="
                                    index < currentStage
                                        ? 'bg-good/12 text-good'
                                        : index === currentStage
                                          ? 'bg-brand text-white'
                                          : 'bg-line/60 text-ink-subtle'
                                "
                            >{{ stage }}</span>
                            <AppIcon
                                v-if="index < stages.length - 1"
                                name="trend"
                                :size="10"
                                class="text-ink-subtle/50"
                            />
                        </li>
                    </ol>
                    <StatusBadge v-else label="Dibatalkan" variant="danger" />

                    <p class="text-right">
                        <span class="block text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            Total nilai
                        </span>
                        <span class="text-2xl font-semibold text-ink tabular-nums">
                            {{ record.currency }} {{ money.format(record.total_amount) }}
                        </span>
                    </p>
                </div>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Informasi PO</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="detail in details" :key="detail.label">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            {{ detail.label }}
                        </dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ detail.value }}</dd>
                    </div>
                    <div v-if="record.remarks" class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">{{ t('common.notes') }}</dt>
                        <dd class="mt-0.5 text-sm leading-relaxed text-ink-muted">{{ record.remarks }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('common.item') }}</h2>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[54rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">#</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.material') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('entity.warehouse') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('po.schedule') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('po.qty') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Diterima</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ t('common.quantity') }}</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in record.items"
                                :key="item.id ?? item.line_no"
                                class="border-b border-line/60 last:border-0"
                            >
                                <td class="px-5 py-3 text-ink-subtle tabular-nums">{{ item.line_no }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-ink">{{ item.material_name }}</span>
                                    <span class="block text-xs text-ink-subtle">{{ item.material_code }}</span>
                                </td>
                                <td class="px-5 py-3 text-ink-muted">{{ item.warehouse_name }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-ink-muted tabular-nums">
                                    {{ dateFmt.format(new Date(item.schedule_delivery_date)) }}
                                </td>
                                <td class="px-5 py-3 text-right text-ink tabular-nums">
                                    {{ money.format(item.qty_ordered) }} {{ item.uom_code }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums"
                                    :class="(item.outstanding ?? 0) > 0 ? 'text-warning' : 'text-ink'">
                                    {{ money.format(item.qty_received ?? 0) }}
                                </td>
                                <td class="px-5 py-3 text-right text-ink tabular-nums">
                                    {{ money.format(item.amount ?? 0) }}
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge
                                        :label="item.overall_status_label ?? '—'"
                                        :variant="item.overall_status_variant ?? 'neutral'"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="record.deliveries.length > 0" class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('entity.delivery') }}</h2>
                </header>
                <ul class="divide-y divide-line/60">
                    <li
                        v-for="delivery in record.deliveries"
                        :key="delivery.ulid"
                        class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                    >
                        <div>
                            <p class="text-sm font-medium text-ink">{{ delivery.delivery_number }}</p>
                            <p class="text-xs text-ink-subtle">
                                {{ dateFmt.format(new Date(delivery.delivery_date)) }}
                            </p>
                        </div>
                        <StatusBadge :label="delivery.status_label" :variant="delivery.status_variant" />
                    </li>
                </ul>
            </section>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirming === 'approve' ? 'Setujui purchase order?' : 'Ajukan untuk approval?'"
            :message="
                confirming === 'approve'
                    ? `${record.po_number} akan disetujui dan mulai dapat menerima delivery.`
                    : `${record.po_number} akan dikirim ke manajemen untuk disetujui. Setelah diajukan, PO tidak dapat diubah tanpa dibatalkan.`
            "
            :confirm-label="confirming === 'approve' ? 'Setujui' : 'Ajukan'"
            :processing="processing"
            @confirm="act(confirming as 'submit' | 'approve')"
            @cancel="confirming = null"
        />

        <!-- Cancellation always asks for a reason: a cancelled order nobody can
             explain later is an audit gap. -->
        <Teleport to="body">
            <div
                v-if="cancelling"
                class="fixed inset-0 z-50 flex items-center justify-center bg-canvas/80 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="cancelling = false"
            >
                <form class="card w-full max-w-md p-6" @submit.prevent="cancel">
                    <h2 class="text-base font-semibold text-ink">Batalkan {{ record.po_number }}?</h2>
                    <p class="mt-1.5 text-sm text-ink-muted">
                        Penerimaan yang sudah tercatat tetap tersimpan. Pembatalan hanya menghentikan sisa pengiriman.
                    </p>

                    <div class="mt-5">
                        <TextareaInput
                            id="cancel-reason"
                            v-model="cancelForm.reason"
                            :label="t('common.cancellation_reason')"
                            :error="cancelForm.errors.reason"
                        />
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                            @click="cancelling = false"
                        >{{ t('common.back') }}</button>
                        <button
                            type="submit"
                            class="rounded-lg bg-critical px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60"
                            :disabled="cancelForm.processing"
                        >
                            {{ cancelForm.processing ? 'Memproses…' : 'Batalkan PO' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
