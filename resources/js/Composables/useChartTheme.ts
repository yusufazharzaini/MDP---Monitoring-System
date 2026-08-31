/**
 * Reads the chart palette from the CSS design tokens.
 *
 * The colours live in one place - resources/css/app.css - and were validated
 * against this app's navy surface with the data-viz palette validator. Charts
 * read them at runtime rather than restating hex values, so a retheme is a
 * single-file change and the charts cannot drift from the UI.
 */
export interface ChartTheme {
    series: [string, string, string];
    grid: string;
    axis: string;
    ink: string;
    inkMuted: string;
    inkSubtle: string;
    surface: string;
    surfaceRaised: string;
    line: string;
    critical: string;
}

function token(name: string, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value === '' ? fallback : value;
}

export function useChartTheme(): ChartTheme {
    return {
        series: [
            token('--color-series-1', '#3987e5'),
            token('--color-series-2', '#d95926'),
            token('--color-series-3', '#199e70'),
        ],
        grid: token('--color-grid', '#1e2b42'),
        axis: token('--color-axis', '#2a3a56'),
        ink: token('--color-ink', '#e8eefb'),
        inkMuted: token('--color-ink-muted', '#93a3bd'),
        inkSubtle: token('--color-ink-subtle', '#64748b'),
        surface: token('--color-surface', '#111a2b'),
        surfaceRaised: token('--color-surface-raised', '#17223a'),
        line: token('--color-line', '#22304d'),
        critical: token('--color-critical', '#d03b3b'),
    };
}

/**
 * Tooltip chrome shared by every chart, so hovering feels the same everywhere.
 */
export function tooltipStyle(theme: ChartTheme): Record<string, unknown> {
    return {
        backgroundColor: theme.surfaceRaised,
        borderColor: theme.line,
        borderWidth: 1,
        padding: [8, 12],
        textStyle: { color: theme.ink, fontSize: 12 },
        extraCssText: 'border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.45);',
    };
}
