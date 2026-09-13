import { describe, it, expect } from "vitest";
import { ref } from "vue";
import { useDragReorder } from "@/composables/useDragReorder";

describe("useDragReorder", () => {
    it("moves the dragged item to the hovered index, shifting the rest, live on dragover", () => {
        const items = ref(["a", "b", "c", "d"]);
        const { onDragStart, onDragOver } = useDragReorder(items);

        onDragStart(0);
        onDragOver(2);

        expect(items.value).toEqual(["b", "c", "a", "d"]);
    });

    it("moving an item later in the list works the same as moving it earlier", () => {
        const items = ref(["a", "b", "c", "d"]);
        const { onDragStart, onDragOver } = useDragReorder(items);

        onDragStart(3);
        onDragOver(0);

        expect(items.value).toEqual(["d", "a", "b", "c"]);
    });

    it("hovering the same index repeatedly is a no-op after the first move", () => {
        const items = ref(["a", "b", "c"]);
        const { onDragStart, onDragOver } = useDragReorder(items);

        onDragStart(0);
        onDragOver(1);
        onDragOver(1);
        onDragOver(1);

        expect(items.value).toEqual(["b", "a", "c"]);
    });

    it("tracks the dragged item across successive moves, so dragging onward keeps working", () => {
        const items = ref(["a", "b", "c", "d"]);
        const { dragIndex, onDragStart, onDragOver } = useDragReorder(items);

        onDragStart(0);
        onDragOver(1);
        expect(items.value).toEqual(["b", "a", "c", "d"]);
        expect(dragIndex.value).toBe(1);

        onDragOver(2);
        expect(items.value).toEqual(["b", "c", "a", "d"]);
        expect(dragIndex.value).toBe(2);
    });

    it("dragover without a prior dragstart is a no-op", () => {
        const items = ref(["a", "b", "c"]);
        const { onDragOver } = useDragReorder(items);

        onDragOver(2);

        expect(items.value).toEqual(["a", "b", "c"]);
    });

    it("clears dragIndex on dragend", () => {
        const items = ref(["a", "b", "c"]);
        const { dragIndex, onDragStart, onDragEnd } = useDragReorder(items);

        onDragStart(0);
        expect(dragIndex.value).toBe(0);
        onDragEnd();

        expect(dragIndex.value).toBeNull();
    });
});
