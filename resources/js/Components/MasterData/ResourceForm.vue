<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

defineProps<{
    title: string;
    subtitle?: string;
    current: string;
    backHref: string;
    processing?: boolean;
    submitLabel?: string;
    hasErrors?: boolean;
}>();

defineEmits<{ submit: [] }>();
</script>

<template>
    <Head :title="title" />

    <AppLayout :current="current" :title="title" :subtitle="subtitle">
        <form class="mx-auto max-w-3xl space-y-5" @submit.prevent="$emit('submit')">
            <div
                v-if="hasErrors"
                class="rounded-lg bg-critical-ground px-4 py-3 text-sm text-critical ring-1 ring-critical/30"
                role="alert"
            >{{ t('msg.check_marked_fields') }}</div>

            <section class="card p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <slot />
                </div>
            </section>

            <div class="flex items-center justify-end gap-2">
                <Link
                    :href="backHref"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                >{{ t('common.cancel') }}</Link>
                <button
                    type="submit"
                    class="rounded-lg bg-brand px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-soft disabled:opacity-60"
                    :disabled="processing"
                >
                    {{ processing ? 'Menyimpan…' : (submitLabel ?? 'Simpan') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
