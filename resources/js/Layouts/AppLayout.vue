<script setup lang="ts">
import { ref } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import Topbar from '@/Components/Topbar.vue';

defineProps<{
    current: string;
    title: string;
    subtitle?: string;
    generatedAt?: string | null;
    refreshing?: boolean;
}>();

defineEmits<{ refresh: [] }>();

const sidebarOpen = ref(false);
</script>

<template>
    <div class="flex min-h-screen bg-canvas">
        <Sidebar :open="sidebarOpen" :current="current" @close="sidebarOpen = false" />

        <div class="flex min-w-0 flex-1 flex-col">
            <Topbar
                :title="title"
                :subtitle="subtitle"
                :generated-at="generatedAt ?? null"
                :refreshing="refreshing"
                @toggle-sidebar="sidebarOpen = !sidebarOpen"
                @refresh="$emit('refresh')"
            />

            <main class="flex-1 px-4 py-5 sm:px-6 sm:py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
