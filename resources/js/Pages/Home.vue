<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedPageProps } from '@/types';

interface ModuleCard {
    key: string;
    label: string;
    description: string;
    permission: string;
}

defineProps<{
    modules: ModuleCard[];
    stats: Array<{ label: string; value: number }>;
}>();

const page = usePage<SharedPageProps>();
const user = computed(() => page.props.auth.user);

const numberFormat = new Intl.NumberFormat('id-ID');

function logout(): void {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Workspace" />

    <div class="min-h-screen bg-canvas">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div>
                    <p class="text-sm font-bold tracking-[0.25em] text-brand">TORICA</p>
                    <p class="text-xs text-ink-subtle">Material Delivery Performance Monitoring</p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-ink">{{ user?.name }}</p>
                        <p class="text-xs text-ink-subtle">{{ user?.roles.join(', ') }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink-muted transition hover:border-danger hover:text-danger"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <h1 class="text-xl font-semibold text-ink">Selamat datang, {{ user?.name }}</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Ringkasan data sistem dan modul yang dapat Anda akses.
            </p>

            <section class="mt-8">
                <h2 class="field-label">Ringkasan data</h2>
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div v-for="stat in stats" :key="stat.label" class="card p-4">
                        <dt class="text-xs font-medium text-ink-subtle">{{ stat.label }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-ink">
                            {{ numberFormat.format(stat.value) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="mt-10">
                <h2 class="field-label">Modul</h2>

                <div v-if="modules.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="module in modules" :key="module.key" class="card p-5">
                        <h3 class="text-sm font-semibold text-ink">{{ module.label }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                            {{ module.description }}
                        </p>
                    </article>
                </div>

                <p v-else class="card p-6 text-sm text-ink-muted">
                    Akun Anda belum memiliki akses ke modul mana pun. Hubungi administrator.
                </p>
            </section>
        </main>
    </div>
</template>
