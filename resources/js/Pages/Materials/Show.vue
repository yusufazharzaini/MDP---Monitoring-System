<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { BadgeVariant } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

interface MaterialRecord {
    id: number;
    ulid: string;
    code: string;
    name: string;
    is_critical: boolean;
    status_label: string;
    status_variant: BadgeVariant;
    [key: string]: string | number | boolean | null;
}

const props = defineProps<{
    record: MaterialRecord;
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const number = new Intl.NumberFormat('id-ID');

const details: Array<{ label: string; value: string }> = [
    { label: 'Kode', value: String(props.record.code) },
    { label: 'Nama', value: String(props.record.name) },
    { label: 'Kategori', value: String(props.record.category_name ?? '—') },
    { label: 'Satuan', value: String(props.record.uom_code ?? '—') },
    { label: 'Minimum stock', value: number.format(Number(props.record.minimum_stock)) },
    { label: 'Critical stock', value: number.format(Number(props.record.critical_stock)) },
    { label: 'Lead time (hari)', value: String(props.record.lead_time_days) },
    { label: 'Spesifikasi', value: String(props.record.specification || '—') },
];
</script>

<template>
    <Head :title="`Material ${record.code}`" />

    <AppLayout current="materials" :title="record.name" :subtitle="`Kode ${record.code}`">
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('materials.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >
                    &larr; Kembali ke daftar material
                </Link>
                <Link
                    v-if="can.update"
                    :href="route('materials.edit', record.ulid)"
                    class="rounded-lg border border-line px-3.5 py-2 text-sm font-semibold text-ink-muted transition hover:border-info hover:text-info"
                >{{ t('material.edit') }}</Link>
            </div>

            <section class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ t('material.detail') }}</h2>
                    <div class="flex items-center gap-2">
                        <StatusBadge
                            v-if="record.is_critical"
                            :label="t('material.critical')"
                            variant="danger"
                        />
                        <StatusBadge :label="record.status_label" :variant="record.status_variant" />
                    </div>
                </div>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="detail in details" :key="detail.label">
                        <dt class="text-[0.65rem] font-semibold tracking-wider text-ink-subtle uppercase">
                            {{ detail.label }}
                        </dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ detail.value }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </AppLayout>
</template>
