<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextareaInput from '@/Components/Form/TextareaInput.vue';
import type { EvaluationRecord } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{
    record: EvaluationRecord;
    can: { regenerate: boolean; approve: boolean; reopen: boolean };
}>();

const score = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

const approving = ref(false);
const reopening = ref(false);
const reopenForm = useForm({ reason: '' });

function approve(): void {
    router.post(
        route('supplier-evaluations.approve', props.record.id),
        {},
        { preserveScroll: true, onSuccess: () => (approving.value = false) },
    );
}

function reopen(): void {
    reopenForm.post(route('supplier-evaluations.reopen', props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            reopening.value = false;
            reopenForm.reset();
        },
    });
}

function regenerate(): void {
    // This supplier only. Posting without one takes the batch branch and
    // recomputes every supplier's scorecard for the period.
    router.post(
        route('supplier-evaluations.store'),
        { period: props.record.period, supplier_id: props.record.supplier_id },
        { preserveScroll: true },
    );
}

const isApproved = computed(() => props.record.status === 'APPROVED');
</script>

<template>
    <Head :title="`${record.supplier_code} ${record.period}`" />

    <AppLayout
        current="supplier-evaluations"
        :title="`Evaluasi ${record.period}`"
        :subtitle="`${record.supplier_name ?? ''} · ${record.supplier_code ?? ''}`"
    >
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('supplier-evaluations.index', { period: record.period })"
                    class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >
                    &larr; Kembali ke daftar evaluasi
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="record.supplier_ulid"
                        :href="route('supplier-performance.show', record.supplier_ulid)"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                    >
                        Lihat scorecard
                    </Link>
                    <button
                        v-if="can.regenerate"
                        type="button"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                        @click="regenerate"
                    >
                        Hitung ulang
                    </button>
                    <button
                        v-if="can.approve"
                        type="button"
                        class="rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft"
                        @click="approving = true"
                    >{{ t('common.approve') }}</button>
                    <button
                        v-if="can.reopen"
                        type="button"
                        class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-critical hover:text-critical"
                        @click="reopening = true"
                    >
                        Buka kembali
                    </button>
                </div>
            </div>

            <div
                v-if="isApproved"
                class="rounded-lg bg-good-ground px-4 py-3 text-sm text-success ring-1 ring-success/30"
                role="status"
            >
                Disetujui oleh {{ record.approved_by ?? '—' }} pada {{ record.approved_at }}. Skor
                ini beku: koreksi data setelah tanggal tersebut tidak mengubahnya.
            </div>
            <div
                v-else
                class="rounded-lg bg-warning-ground px-4 py-3 text-sm text-warning ring-1 ring-warning/30"
                role="status"
            >
                Masih draft. Skor akan dihitung ulang dari transaksi setiap kali evaluasi periode
                ini dijalankan, sampai disetujui.
            </div>

            <section class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            Total skor
                        </p>
                        <p class="mt-1 flex items-baseline gap-3">
                            <span class="text-4xl font-semibold text-ink tabular-nums">
                                {{ score.format(record.total_score) }}
                            </span>
                            <StatusBadge :label="record.grade_label" :variant="record.grade_variant" />
                            <StatusBadge :label="record.status_label" :variant="record.status_variant" />
                        </p>
                    </div>

                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                                Dihitung oleh
                            </dt>
                            <dd class="mt-0.5 text-sm text-ink">{{ record.created_by ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">{{ t('common.approved_by') }}</dt>
                            <dd class="mt-0.5 text-sm text-ink">{{ record.approved_by ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="record.remarks" class="mt-4 border-t border-line pt-4">
                    <p class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">{{ t('common.notes') }}</p>
                    <p class="mt-0.5 text-sm leading-relaxed text-ink-muted">{{ record.remarks }}</p>
                </div>
            </section>

            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">Rincian Kriteria</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Kontribusi setiap kriteria terhadap total, dihitung di backend.
                    </p>
                </header>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[0.65rem] tracking-wider text-ink-subtle uppercase">
                                <th scope="col" class="px-5 py-3 text-left font-semibold">Kriteria</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Bobot</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Skor</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Kontribusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in record.criteria"
                                :key="item.criteria_name"
                                class="border-b border-line/60 last:border-0"
                            >
                                <td class="px-5 py-3 font-medium text-ink">{{ item.criteria_name }}</td>
                                <td class="px-5 py-3 text-right text-ink-muted tabular-nums">{{ item.weight }}%</td>
                                <td class="px-5 py-3 text-right text-ink tabular-nums">{{ score.format(item.score) }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-ink tabular-nums">
                                    {{ score.format(item.weighted) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <ConfirmDialog
            :open="approving"
            title="Setujui evaluasi"
            :message="`Skor periode ${record.period} akan dibekukan. Koreksi data setelah ini tidak lagi mengubahnya.`"
            confirm-label="Setujui"
            tone="brand"
            @cancel="approving = false"
            @confirm="approve"
        />

        <ConfirmDialog
            :open="reopening"
            title="Buka kembali evaluasi"
            :message="`Evaluasi ${record.period} kembali menjadi draft dan dapat dihitung ulang. Sebutkan alasannya untuk jejak audit.`"
            confirm-label="Buka kembali"
            :processing="reopenForm.processing"
            @cancel="reopening = false"
            @confirm="reopen"
        >
            <TextareaInput
                id="reopen_reason"
                v-model="reopenForm.reason"
                :label="t('common.reason')"
                required
                :error="reopenForm.errors.reason"
            />
        </ConfirmDialog>
    </AppLayout>
</template>
