<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedPageProps } from '@/Types';

defineProps<{ title: string; subtitle?: string }>();

const page = usePage<SharedPageProps>();
const appName = computed(() => page.props.app.name);
</script>

<template>
    <div class="min-h-screen bg-canvas">
        <div class="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-12">
            <div class="grid w-full gap-10 lg:grid-cols-2 lg:gap-16">
                <!-- Brand panel: hidden on small screens where it costs more than it tells -->
                <section class="hidden flex-col justify-center lg:flex">
                    <p class="text-2xl font-bold tracking-[0.3em] text-brand">TORICA</p>
                    <h1 class="mt-6 text-3xl font-semibold leading-tight text-ink">
                        Material Delivery Performance Monitoring
                    </h1>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-ink-muted">
                        Monitor ketepatan pengiriman material dari supplier ke plant: service rate,
                        keterlambatan, quantity shortage, analisa masalah, dan performa supplier.
                    </p>
                    <dl class="mt-10 grid grid-cols-3 gap-4 border-t border-line pt-8">
                        <div v-for="metric in ['Service Rate', 'Supplier KPI', 'Problem Analysis']" :key="metric">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-ink-subtle">
                                {{ metric }}
                            </dt>
                        </div>
                    </dl>
                </section>

                <section class="flex items-center">
                    <div class="card w-full p-8">
                        <p class="text-lg font-bold tracking-[0.25em] text-brand lg:hidden">TORICA</p>
                        <h2 class="mt-2 text-xl font-semibold text-ink lg:mt-0">{{ title }}</h2>
                        <p v-if="subtitle" class="mt-1 text-sm text-ink-muted">{{ subtitle }}</p>

                        <div class="mt-8">
                            <slot />
                        </div>

                        <p class="mt-8 border-t border-line pt-4 text-xs text-ink-subtle">
                            {{ appName }}
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
