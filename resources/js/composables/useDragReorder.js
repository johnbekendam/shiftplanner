import { ref } from 'vue'

/**
 * HTML5 native drag-and-drop reordering for a flat list. `items` is the
 * Ref to reorder in place. The list reorders live as the dragged row
 * crosses another row (on dragover), the way sortable lists usually
 * feel, rather than waiting for the drop to land. `dragIndex` tracks
 * the row currently being dragged, for a caller to show it as lifted
 * (e.g. reduced opacity) until dragend.
 */
export function useDragReorder(items) {
    const dragIndex = ref(null)

    function onDragStart(index) {
        dragIndex.value = index
    }

    function onDragOver(index) {
        if (dragIndex.value === null || dragIndex.value === index) return

        const next = [...items.value]
        const [moved] = next.splice(dragIndex.value, 1)
        next.splice(index, 0, moved)
        items.value = next
        dragIndex.value = index
    }

    function onDragEnd() {
        dragIndex.value = null
    }

    return { dragIndex, onDragStart, onDragOver, onDragEnd }
}
