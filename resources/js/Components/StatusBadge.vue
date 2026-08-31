<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import type { BadgeVariant } from '@/types';

const props = withDefaults(
    defineProps<{ label: string; variant?: BadgeVariant; icon?: boolean }>(),
    { variant: 'neutral', icon: true },
);

/**
 * Colour never carries the meaning on its own: every badge pairs its colour
 * with an icon and the status word itself.
 */
const styles: Record<BadgeVariant, { ring: string; text: string; ground: string; icon: string }> = {
    success: { ring: 'ring-good/40', text: 'text-good', ground: 'bg-good/12', icon: 'good' },
    danger: { ring: 'ring-critical/40', text: 'text-critical', ground: 'bg-critical/12', icon: 'critical' },
    warning: { ring: 'ring-warning/40', text: 'text-warning', ground: 'bg-warning/12', icon: 'warning' },
    info: { ring: 'ring-info/40', text: 'text-info', ground: 'bg-info/12', icon: 'info' },
    neutral: { ring: 'ring-ink-subtle/30', text: 'text-ink-muted', ground: 'bg-ink-subtle/10', icon: 'neutral' },
};

const style = computed(() => styles[props.variant] ?? styles.neutral);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-semibold ring-1 whitespace-nowrap"
        :class="[style.ground, style.text, style.ring]"
    >
        <AppIcon v-if="icon" :name="style.icon" :size="12" />
        {{ label }}
    </span>
</template>
