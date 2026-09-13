import { describe, it, expect } from "vitest";
import { ref } from "vue";
import { useDragReorder } from "@/composables/useDragReorder";

describe("useDragReorder", () => {
    it("moves the dragged item to the drop index, shifting the rest", () => {
        const items = ref(["a", "b", "c", "d"]);
        const { onDragStart, onDrop } = useDragReorder(items);

        onDragStart(0);
        onDrop(2);

        expect(items.value).toEqual(["b", "c", "a", "d"]);
    });

    it("moving an item later in the list works the same as moving it earlier", () => {
        const items = ref(["a", "b", "c", "d"]);
        const { onDragStart, onDrop } = useDragReorder(items);

        onDragStart(3);
        onDrop(0);

        expect(items.value).toEqual(["d", "a", "b", "c"]);
    });

    it("dropping on the same index it started from is a no-op", () => {
        const items = ref(["a", "b", "c"]);
        const { onDragStart, onDrop } = useDragReorder(items);

        onDragStart(1);
        onDrop(1);

        expect(items.value).toEqual(["a", "b", "c"]);
    });

    it("dropping without a prior dragstart is a no-op", () => {
        const items = ref(["a", "b", "c"]);
        const { onDrop } = useDragReorder(items);

        onDrop(2);

        expect(items.value).toEqual(["a", "b", "c"]);
    });

    it("clears dragIndex after a drop", () => {
        const items = ref(["a", "b", "c"]);
        const { dragIndex, onDragStart, onDrop } = useDragReorder(items);

        onDragStart(0);
        expect(dragIndex.value).toBe(0);
        onDrop(2);

        expect(dragIndex.value).toBeNull();
    });
});
