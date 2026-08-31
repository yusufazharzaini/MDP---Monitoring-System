<script setup lang="ts">
import { computed } from 'vue';
import type { EChartsCoreOption } from 'echarts/core';
import BaseChart from '@/Components/Charts/BaseChart.vue';
import { tooltipStyle, useChartTheme } from '@/Composables/useChartTheme';
import type { TrendPoint } from '@/Types';

const props = defineProps<{ points: TrendPoint[]; target: number }>();

const theme = useChartTheme();

/**
 * One series, so no legend box - the panel title already says what is plotted.
 * The target arrives from kpi_settings and is drawn as a reference line, which
 * is how a reader sees which months fell short without the line changing colour.
 */
const option = computed<EChartsCoreOption>(() => {
    const values = props.points.map((point) => point.service_rate);
    const observed = values.filter((value): value is number => value !== null);

    // Zoom the axis to the data with a little air, but never below the target -
    // a y-axis that hides the target line would flatter the numbers.
    const floor = Math.min(...observed, props.target);
    const ceiling = Math.max(...observed, props.target);
    const pad = Math.max(1, (ceiling - floor) * 0.35);

    return {
        grid: { top: 28, right: 56, bottom: 28, left: 44 },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'line', lineStyle: { color: theme.axis, width: 1 } },
            ...tooltipStyle(theme),
            formatter: (params: unknown) => {
                const items = params as Array<{ dataIndex: number }>;
                const point = props.points[items[0]?.dataIndex ?? 0];

                if (!point) {
                    return '';
                }

                const rate =
                    point.service_rate === null
                        ? '<span style="color:' + theme.inkSubtle + '">Tidak ada delivery</span>'
                        : `<strong>${point.service_rate.toFixed(1)}%</strong>`;

                return [
                    `<div style="font-weight:600;margin-bottom:4px">${point.period}</div>`,
                    `<div>Service rate: ${rate}</div>`,
                    `<div style="color:${theme.inkMuted}">On time ${point.on_time_delivery.toLocaleString('id-ID')} dari ${point.total_delivery.toLocaleString('id-ID')}</div>`,
                ].join('');
            },
        },
        xAxis: {
            type: 'category',
            data: props.points.map((point) => point.label),
            boundaryGap: false,
            axisLine: { lineStyle: { color: theme.axis, width: 1 } },
            axisTick: { show: false },
            axisLabel: { color: theme.inkMuted, fontSize: 11 },
        },
        yAxis: {
            type: 'value',
            min: Math.max(0, Math.floor(floor - pad)),
            max: Math.min(100, Math.ceil(ceiling + pad)),
            splitLine: { lineStyle: { color: theme.grid, width: 1, type: 'solid' } },
            axisLabel: { color: theme.inkMuted, fontSize: 11, formatter: '{value}%' },
        },
        series: [
            {
                name: 'Service rate',
                type: 'line',
                data: values,
                connectNulls: false,
                smooth: false,
                // Mark specs: 2px line, round caps, >=8px markers ringed in the surface colour.
                lineStyle: { width: 2, cap: 'round', join: 'round', color: theme.series[2] },
                itemStyle: { color: theme.series[2], borderColor: theme.surface, borderWidth: 2 },
                symbol: 'circle',
                symbolSize: 9,
                areaStyle: { color: theme.series[2], opacity: 0.1 },
                // A single direct label at the end: the current period's value.
                endLabel: {
                    show: true,
                    color: theme.ink,
                    fontSize: 12,
                    fontWeight: 600,
                    formatter: (params: { value: number | null }) =>
                        params.value === null ? '' : `${Number(params.value).toFixed(1)}%`,
                },
                markLine: {
                    silent: true,
                    symbol: 'none',
                    label: {
                        position: 'insideEndTop',
                        color: theme.inkMuted,
                        fontSize: 10,
                        formatter: `Target ${props.target}%`,
                    },
                    lineStyle: { color: theme.critical, type: 'dashed', width: 1 },
                    data: [{ yAxis: props.target }],
                },
            },
        ],
    };
});
</script>

<template>
    <div>
        <BaseChart
            :option="option"
            height="17rem"
            aria-label="Tren service rate bulanan terhadap target"
        />

        <!-- Table view: the same numbers, available to screen readers and to
             anyone who cannot read the chart. -->
        <table class="sr-only">
            <caption>Tren service rate bulanan</caption>
            <thead>
                <tr><th>Periode</th><th>Total delivery</th><th>On time</th><th>Service rate</th><th>Target</th></tr>
            </thead>
            <tbody>
                <tr v-for="point in points" :key="point.period">
                    <td>{{ point.period }}</td>
                    <td>{{ point.total_delivery }}</td>
                    <td>{{ point.on_time_delivery }}</td>
                    <td>{{ point.service_rate === null ? 'tidak ada data' : `${point.service_rate}%` }}</td>
                    <td>{{ point.target }}%</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
