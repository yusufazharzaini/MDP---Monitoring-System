<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        message: string;
        confirmLabel?: string;
        processing?: boolean;
        /** Destructive by default; 'brand' for a confirmation that is not a loss. */
        tone?: 'critical' | 'brand';
    }>(),
    { tone: 'critical' },
);

const emit = defineEmits<{ confirm: []; cancel: [] }>();

/** Escape closes the dialog, which is what a keyboard user will reach for. */
function onKeydown(event: KeyboardEvent): void {
    if (props.open && event.key === 'Escape') {
        emit('cancel');
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-canvas/80 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            @click.self="$emit('cancel')"
        >
            <div class="card w-full max-w-md p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full"
                        :class="tone === 'brand' ? 'bg-brand/15 text-brand' : 'bg-critical/12 text-critical'"
                    >
                        <AppIcon :name="tone === 'brand' ? 'good' : 'warning'" :size="20" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">{{ title }}</h2>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">{{ message }}</p>
                    </div>
                </div>

                <!-- Anything the confirmation needs to collect, such as a reason. -->
                <div v-if="$slots.default" class="mt-5">
                    <slot />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition hover:text-ink"
                        :disabled="processing"
                        @click="$emit('cancel')"
                    >{{ t('common.cancel') }}</button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60"
                        :class="tone === 'brand' ? 'bg-brand' : 'bg-critical'"
                        :disabled="processing"
                        @click="$emit('confirm')"
                    >
                        {{ processing ? 'Memproses…' : (confirmLabel ?? 'Hapus') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
