<script setup lang="ts">
/**
 * Inline icon set. Status colour never travels alone in this app - every
 * status badge and KPI card pairs its colour with one of these plus a text
 * label, which is what keeps red and green distinguishable under
 * deuteranopia.
 */
const paths: Record<string, string> = {
    dashboard: 'M3 12h7V3H3v9Zm0 9h7v-7H3v7Zm11 0h7V12h-7v9Zm0-18v7h7V3h-7Z',
    supplier: 'M3 7h13v10H3V7Zm13 3h3l2 3v4h-5v-7ZM6.5 20a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z',
    material: 'M12 2 3 7v10l9 5 9-5V7l-9-5Zm0 2.3L18.5 8 12 11.7 5.5 8 12 4.3ZM5 9.7l6 3.4v6.2l-6-3.3V9.7Zm8 9.6v-6.2l6-3.4v6.3l-6 3.3Z',
    order: 'M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM9 12h8v1.6H9V12Zm0 4h8v1.6H9V16Z',
    delivery: 'M3 6h11v9H3V6Zm12 3h3.2l2.3 3.2V15H15V9ZM7 19.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Zm10 0a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z',
    problem: 'M12 2 1 21h22L12 2Zm0 5 7.5 12.9h-15L12 7Zm-.9 4v4.5h1.8V11h-1.8Zm0 5.6v1.8h1.8v-1.8h-1.8Z',
    report: 'M5 3h14v18H5V3Zm2.5 4.5v9H10v-9H7.5Zm4 3v6H14v-6h-2.5Zm4-1.5v7.5H18V9h-2.5Z',
    settings: 'M12 8.5A3.5 3.5 0 1 0 12 15.5 3.5 3.5 0 0 0 12 8.5Zm9 4.6v-2.2l-2.4-.4a6.8 6.8 0 0 0-.7-1.7l1.4-2-1.6-1.6-2 1.4a6.8 6.8 0 0 0-1.7-.7L13.6 3h-2.2l-.4 2.4a6.8 6.8 0 0 0-1.7.7l-2-1.4-1.6 1.6 1.4 2a6.8 6.8 0 0 0-.7 1.7L3 10.9v2.2l2.4.4c.16.6.4 1.17.7 1.7l-1.4 2 1.6 1.6 2-1.4c.53.3 1.1.54 1.7.7l.4 2.4h2.2l.4-2.4a6.8 6.8 0 0 0 1.7-.7l2 1.4 1.6-1.6-1.4-2c.3-.53.54-1.1.7-1.7l2.4-.4Z',

    good: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.4-4-4 1.5-1.5 2.5 2.5 5.4-5.4 1.5 1.5-6.9 6.9Z',
    warning: 'M12 2 1 21h22L12 2Zm-.9 6h1.8v6.3h-1.8V8Zm0 8.1h1.8v1.9h-1.8v-1.9Z',
    critical: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z',
    info: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z',
    neutral: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-5 9h10v2H7v-2Z',

    clock: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 10.6V6h-2v7.4l5 3 1-1.7-4-2.1Z',
    refresh: 'M12 5V2L8 6l4 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7Z',
    filter: 'M3 5h18l-7 8v6l-4-2v-4L3 5Z',
    trend: 'M3 17.5 9.5 11l4 4L21 7.5 19.6 6l-6.1 6.1-4-4L2 16.1l1 1.4Z',
    truck: 'M3 6h11v9H3V6Zm12 3h3.2l2.3 3.2V15H15V9ZM7 19.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Zm10 0a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z',
    box: 'M12 2 3 7v10l9 5 9-5V7l-9-5Zm0 2.3L18.5 8 12 11.7 5.5 8 12 4.3Z',
    logout: 'M10 3h8v18h-8v-2h6V5h-6V3Zm-1 5.6L11.4 11H3v2h8.4L9 15.4 10.4 17l5-5-5-5L9 8.6Z',
    menu: 'M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z',
    close: 'M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7l1.4-1.4 6.3 6.3 6.3-6.3 1.4 1.4Z',
};

withDefaults(defineProps<{ name: keyof typeof paths | string; size?: number }>(), { size: 16 });
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
        focusable="false"
        class="shrink-0"
    >
        <path :d="paths[name] ?? paths.neutral" />
    </svg>
</template>
