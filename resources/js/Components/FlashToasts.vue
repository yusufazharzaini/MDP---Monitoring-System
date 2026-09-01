<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import type { SharedPageProps } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

/**
 * Surfaces the flash messages the controllers set, including the ones a
 * refused delete produces - a business rule that fires must be visible, not
 * swallowed by a redirect.
 */
const page = usePage<SharedPageProps>();
const dismissed = ref(false);

const flash = computed(() => {
    const { success, error, warning } = page.props.flash;

    if (success) return { text: success, variant: 'success' as const, icon: 'good' };
    if (error) return { text: error, variant: 'danger' as const, icon: 'critical' };
    if (warning) return { text: warning, variant: 'warning' as const, icon: 'warning' };

    return null;
});

watch(flash, () => (dismissed.value = false));

const tone = {
    success: 'bg-good-ground text-good ring-good/30',
    danger: 'bg-critical-ground text-critical ring-critical/30',
    warning: 'bg-warning-ground text-warning ring-warning/30',
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0 translate-y-2"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
    >
        <div
            v-if="flash && !dismissed"
            class="fixed right-4 bottom-4 z-50 flex max-w-sm items-start gap-3 rounded-lg px-4 py-3 shadow-lg ring-1"
            :class="tone[flash.variant]"
            role="status"
        >
            <AppIcon :name="flash.icon" :size="18" class="mt-0.5 shrink-0" />
            <p class="text-sm leading-relaxed">{{ flash.text }}</p>
            <button
                type="button"
                class="shrink-0 opacity-70 transition hover:opacity-100"
                :aria-label="t('notification.dismiss')"
                @click="dismissed = true"
            >
                <AppIcon name="close" :size="14" />
            </button>
        </div>
    </Transition>
</template>
