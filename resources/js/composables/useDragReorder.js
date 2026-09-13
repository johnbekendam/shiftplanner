import { ref } from 'vue'

/**
 * HTML5 native drag-and-drop reordering for a flat list. `items` is the
 * Ref to reorder in place. Dropping on the row you started from is a
 * no-op; dropping elsewhere splices the dragged row to that position.
 */
export function useDragReorder(items) {
    const dragIndex = ref(null)

    function onDragStart(index) {
        dragIndex.value = index
    }

    function onDrop(index) {
        if (dragIndex.value === null || dragIndex.value === index) {
            dragIndex.value = null
            return
        }

        const next = [...items.value]
        const [moved] = next.splice(dragIndex.value, 1)
        next.splice(index, 0, moved)
        items.value = next
        dragIndex.value = null
    }

    return { dragIndex, onDragStart, onDrop }
}
