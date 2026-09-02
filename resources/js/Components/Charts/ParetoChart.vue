<script setup lang="ts">
import { computed } from 'vue';
import type { EChartsCoreOption } from 'echarts/core';
import BaseChart from '@/Components/Charts/BaseChart.vue';
import { tooltipStyle, useChartTheme } from '@/Composables/useChartTheme';
import { useIsNarrow } from '@/Composables/useIsNarrow';
import type { ParetoDataset } from '@/Types';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps<{ dataset: ParetoDataset }>();

const theme = useChartTheme();
const isNarrow = useIsNarrow();

/**
 * A Pareto chart is classically drawn with counts on a left axis and cumulative
 * percentage on a right one. Two y-scales on one plot invent a relationship the
 * data does not contain, so both marks are expressed on a single 0-100% axis
 * instead: the bar is each category's share, the line is the running share.
 *
 * Nothing is lost - the raw counts sit in the table beside this chart, which is
 * also what keeps the values reachable without hovering.
 */
const option = computed<EChartsCoreOption>(() => {
    const categories = props.dataset.categories;

    return {
        grid: { top: 34, right: 20, bottom: isNarrow.value ? 84 : 56, left: 44 },
        legend: {
            top: 0,
            left: 0,
            itemWidth: 12,
            itemHeight: 12,
            itemGap: 18,
            textStyle: { color: theme.inkMuted, fontSize: 11 },
            data: ['Porsi masalah', 'Kumulatif'],
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            ...tooltipStyle(theme),
            formatter: (params: unknown) => {
                const items = params as Array<{ dataIndex: number }>;
                const row = categories[items[0]?.dataIndex ?? 0];

                if (!row) {
                    return '';
                }

                return [
                    `<div style="font-weight:600;margin-bottom:4px">${row.category}</div>`,
                    `<div>Jumlah: <strong>${row.count}</strong> kejadian</div>`,
                    `<div>Porsi: ${row.percentage.toFixed(1)}%</div>`,
                    `<div>Kumulatif: ${row.cumulative_percentage.toFixed(1)}%</div>`,
                    row.is_vital_few
                        ? `<div style="color:${theme.inkMuted};margin-top:4px">Termasuk vital few (&le; ${props.dataset.threshold}%)</div>`
                        : '',
                ].join('');
            },
        },
        xAxis: {
            type: 'category',
            data: categories.map((row) => row.category),
            axisLine: { lineStyle: { color: theme.axis, width: 1 } },
            axisTick: { show: false },
            // Narrow viewports cannot fit five wrapped category names side by
            // side, so the labels rotate and truncate; the full name stays in
            // the tooltip and in the table beneath the chart.
            axisLabel: isNarrow.value
                ? {
                      color: theme.inkMuted,
                      fontSize: 10,
                      interval: 0,
                      rotate: 38,
                      width: 76,
                      overflow: 'truncate',
                      ellipsis: '…',
                  }
                : {
                      color: theme.inkMuted,
                      fontSize: 10,
                      interval: 0,
                      width: 92,
                      overflow: 'break',
                      lineHeight: 13,
                  },
        },
        yAxis: {
            type: 'value',
            min: 0,
            max: 100,
            splitLine: { lineStyle: { color: theme.grid, width: 1, type: 'solid' } },
            axisLabel: { color: theme.inkMuted, fontSize: 11, formatter: '{value}%' },
        },
        series: [
            {
                name: 'Porsi masalah',
                type: 'bar',
                data: categories.map((row) => row.percentage),
                // Mark specs: capped width, rounded data-end, square at the baseline.
                barMaxWidth: 24,
                itemStyle: { color: theme.series[0], borderRadius: [4, 4, 0, 0] },
            },
            {
                name: 'Kumulatif',
                type: 'line',
                data: categories.map((row) => row.cumulative_percentage),
                lineStyle: { width: 2, cap: 'round', join: 'round', color: theme.series[1] },
                itemStyle: { color: theme.series[1], borderColor: theme.surface, borderWidth: 2 },
                symbol: 'circle',
                symbolSize: 9,
                z: 3,
                markLine: {
                    silent: true,
                    symbol: 'none',
                    label: {
                        position: 'insideEndTop',
                        color: theme.inkMuted,
                        fontSize: 10,
                        formatter: `${props.dataset.threshold}%`,
                    },
                    lineStyle: { color: theme.axis, type: 'dashed', width: 1 },
                    data: [{ yAxis: props.dataset.threshold }],
                },
            },
        ],
    };
});
</script>

<template>
    <BaseChart
        :option="option"
        height="17rem"
        :aria-label="t('chart.pareto_desc')"
    />
</template>
