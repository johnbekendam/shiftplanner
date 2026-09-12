import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "tags.empty": "No tags have been set up yet.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import TagChecklist from "@/components/TagChecklist.vue";
import { CheckboxInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Forklift" },
    { id: 2, name: "Cleanroom" },
    { id: 3, name: "First aid" },
];

const mountList = (props = {}) =>
    mount(TagChecklist, { props: { items, selectedIds: [2], emptyKey: "tags.empty", ...props } });

describe("TagChecklist", () => {
    it("renders a checkbox per item with its name", () => {
        const w = mountList();
        expect(w.findAllComponents(CheckboxInput)).toHaveLength(3);
        expect(w.text()).toContain("Forklift");
        expect(w.text()).toContain("First aid");
    });

    it("checks the items the employee has selected", () => {
        const w = mountList();
        const boxes = w.findAllComponents(CheckboxInput);
        expect(boxes[0].props("modelValue")).toBe(false);
        expect(boxes[1].props("modelValue")).toBe(true);
        expect(boxes[2].props("modelValue")).toBe(false);
    });

    it("shows the empty message for its emptyKey when there are no items", () => {
        const w = mountList({ items: [], selectedIds: [] });
        expect(w.text()).toContain("No tags have been set up yet.");
    });

    it("checks the box locally and emits update:selectedIds, without a network call", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(w.findAllComponents(CheckboxInput)[0].props("modelValue")).toBe(true);
        expect(w.emitted("update:selectedIds").at(-1)[0]).toEqual([2, 1]);
    });

    it("does not toggle and disables the boxes when disabled", async () => {
        const w = mountList({ disabled: true });
        const boxes = w.findAllComponents(CheckboxInput);
        expect(boxes.every((b) => b.props("disabled") === true)).toBe(true);

        boxes[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(w.emitted("update:selectedIds")).toBeUndefined();
    });

    it("unchecks the box locally and emits update:selectedIds", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[1].vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        expect(w.emitted("update:selectedIds").at(-1)[0]).toEqual([]);
    });
});
