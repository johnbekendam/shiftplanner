import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "widgets.name": "Name",
    "widgets.add": "Add widget",
    "widgets.add_placeholder": "New widget",
    "widgets.drag_handle": "Drag to reorder",
    "widgets.delete": "Delete",
    "widgets.list_empty": "No widgets yet.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import OrderedNameList from "@/components/OrderedNameList.vue";
import { TextInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Forklift", position: 1, holder_count: 3 },
    { id: 2, name: "Cleanroom", position: 2, holder_count: 0 },
    { id: 3, name: "First aid", position: 3, holder_count: 1 },
];

const mountList = (props = {}) => mount(OrderedNameList, { props: { items, i18nPrefix: "widgets", ...props } });

const rowInputs = (w) =>
    w.findAll('[data-testid="ordered-name-row"]').map((r) => r.findComponent(TextInput));

describe("OrderedNameList", () => {
    it("renders a row per item and the prefixed name header", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="ordered-name-row"]')).toHaveLength(3);
        expect(w.text()).toContain("Name");
    });

    it("shows an empty state with no items", () => {
        const w = mountList({ items: [] });
        expect(w.text()).toContain("No widgets yet.");
    });

    it("adds a new row locally and emits update:items, without a network call", async () => {
        const w = mountList({ items: [] });
        w.findAllComponents(TextInput)[0].vm.$emit("update:modelValue", "Welding");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="ordered-name-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toMatchObject([{ id: null, name: "Welding" }]);
    });

    it("does not add a row with a blank name", async () => {
        const w = mountList({ items: [] });
        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="ordered-name-row"]')).toHaveLength(0);
        expect(w.emitted("update:items")).toBeUndefined();
    });

    it("renames a row locally and emits update:items, without a network call", async () => {
        const w = mountList();
        rowInputs(w)[0].vm.$emit("update:modelValue", "Forklift licence");
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0][0]).toMatchObject({ id: 1, name: "Forklift licence" });
    });

    it("reorders rows on drag-and-drop and emits update:items", async () => {
        const w = mountList();
        const rows = w.findAll('[data-testid="ordered-name-row"]');

        await rows[0].trigger("dragstart");
        await rows[2].trigger("dragover");

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0].map((r) => r.id)).toEqual([2, 3, 1]);
    });

    it("removes a row locally and emits update:items, without a confirmation or network call", async () => {
        const w = mountList();
        await w.findAll('[data-testid="ordered-name-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(w.findAll('[data-testid="ordered-name-row"]')).toHaveLength(2);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toEqual([items[0], items[2]]);
    });
});
