<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import type { BadgeVariant } from '@/types';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        unit?: string;
        caption?: string;
        variant?: BadgeVariant;
        icon?: string;
        /** The one hero figure of the view - exactly one card should set this. */
        hero?: boolean;
        statusLabel?: string;
    }>(),
    { variant: 'neutral', icon: 'box', hero: false },
);

/**
 * Status colour is never the only channel: each card pairs its colour with an
 * icon and, when it carries a verdict, a status word.
 */
const palette: Record<BadgeVariant, { ground: string; ink: string; ring: string }> = {
    success: { ground: 'bg-good-ground', ink: 'text-good', ring: 'ring-good/30' },
    danger: { ground: 'bg-critical-ground', ink: 'text-critical', ring: 'ring-critical/30' },
    warning: { ground: 'bg-warning-ground', ink: 'text-warning', ring: 'ring-warning/30' },
    info: { ground: 'bg-info-ground', ink: 'text-info', ring: 'ring-info/30' },
    neutral: { ground: 'bg-neutral-ground', ink: 'text-ink-muted', ring: 'ring-line' },
};

const tone = computed(() => palette[props.variant] ?? palette.neutral);
</script>

<template>
    <article
        class="card flex flex-col justify-between gap-4 p-5 ring-1"
        :class="[hero ? tone.ground : 'bg-surface', tone.ring]"
    >
        <div class="flex items-start justify-between gap-3">
            <p class="text-[0.7rem] font-semibold tracking-wider text-ink-muted uppercase">
                {{ label }}
            </p>
            <span
                class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                :class="[tone.ink, hero ? 'bg-canvas/40' : 'bg-line/60']"
            >
                <AppIcon :name="icon" :size="16" />
            </span>
        </div>

        <div>
            <p class="flex items-baseline gap-1">
                <!-- Hero figure: >=48px, exactly one per view. -->
                <span
                    class="font-semibold tabular-nums"
                    :class="hero ? 'text-5xl text-ink' : 'text-3xl text-ink'"
                >{{ value }}</span>
                <span v-if="unit" class="text-lg font-medium" :class="tone.ink">{{ unit }}</span>
            </p>

            <p v-if="caption || statusLabel" class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
                <span v-if="statusLabel" class="inline-flex items-center gap-1 font-semibold" :class="tone.ink">
                    <AppIcon :name="variant === 'success' ? 'good' : variant === 'neutral' ? 'neutral' : variant === 'info' ? 'info' : variant" :size="12" />
                    {{ statusLabel }}
                </span>
                <span v-if="caption" class="text-ink-muted">{{ caption }}</span>
            </p>
        </div>
    </article>
</template>
