<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { use } from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    TooltipComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import { init, type ECharts, type EChartsCoreOption } from 'echarts/core';

// Tree-shaken registration: only the pieces these two charts actually use.
use([BarChart, LineChart, GridComponent, LegendComponent, MarkLineComponent, TooltipComponent, CanvasRenderer]);

const props = withDefaults(
    defineProps<{ option: EChartsCoreOption; height?: string; ariaLabel?: string }>(),
    { height: '18rem' },
);

const host = ref<HTMLDivElement | null>(null);
const chart = shallowRef<ECharts | null>(null);
let observer: ResizeObserver | undefined;

onMounted(() => {
    if (!host.value) {
        return;
    }

    chart.value = init(host.value, undefined, { renderer: 'canvas' });
    chart.value.setOption(props.option);

    // Redraw on container resize, which is what makes the panel responsive
    // inside a CSS grid that the window resize event alone would not catch.
    observer = new ResizeObserver(() => chart.value?.resize());
    observer.observe(host.value);
});

watch(
    () => props.option,
    (option) => chart.value?.setOption(option, true),
    { deep: true },
);

onBeforeUnmount(() => {
    observer?.disconnect();
    chart.value?.dispose();
    chart.value = null;
});
</script>

<template>
    <div
        ref="host"
        :style="{ height }"
        class="w-full"
        role="img"
        :aria-label="ariaLabel"
    />
</template>
