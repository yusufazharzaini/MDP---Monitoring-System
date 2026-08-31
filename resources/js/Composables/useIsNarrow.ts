import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Tracks whether the viewport is below a breakpoint, so a chart can adapt its
 * own labelling rather than relying on CSS it cannot see.
 */
export function useIsNarrow(maxWidth = 640) {
    const isNarrow = ref(false);
    let query: MediaQueryList | undefined;

    function update(event: MediaQueryList | MediaQueryListEvent): void {
        isNarrow.value = event.matches;
    }

    onMounted(() => {
        query = window.matchMedia(`(max-width: ${maxWidth}px)`);
        update(query);
        query.addEventListener('change', update);
    });

    onBeforeUnmount(() => query?.removeEventListener('change', update));

    return isNarrow;
}
