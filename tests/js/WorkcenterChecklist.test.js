import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "workcenters.checklist_empty": "No workcenters have been set up yet.",
    "workcenters.archived_suffix": ":name (archived)",
    "workcenters.mode.hard": "Requirement",
    "workcenters.mode.soft": "Preference",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import WorkcenterChecklist from "@/components/WorkcenterChecklist.vue";
import { CheckboxInput, SelectInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Assembly A", archived: false },
    { id: 2, name: "Paint Booth", archived: false },
    { id: 3, name: "Old Line", archived: true },
];

const mountList = (props = {}) =>
    mount(WorkcenterChecklist, {
        props: { items, selectedRows: [{ workcenter_id: 2, mode: "hard" }], emptyKey: "workcenters.checklist_empty", ...props },
    });

describe("WorkcenterChecklist", () => {
    it("renders a checkbox per item with its name", () => {
        const w = mountList();
        expect(w.findAllComponents(CheckboxInput)).toHaveLength(3);
        expect(w.text()).toContain("Assembly A");
        expect(w.text()).toContain("Paint Booth");
    });

    it("suffixes an archived item's label", () => {
        const w = mountList();
        expect(w.text()).toContain("Old Line (archived)");
    });

    it("checks the rows the employee holds and shows no mode select for the rest", () => {
        const w = mountList();
        const boxes = w.findAllComponents(CheckboxInput);
        expect(boxes[0].props("modelValue")).toBe(false);
        expect(boxes[1].props("modelValue")).toBe(true);
        expect(boxes[2].props("modelValue")).toBe(false);
        expect(w.findAllComponents(SelectInput)).toHaveLength(1);
    });

    it("shows the held row's mode in its select", () => {
        const w = mountList();
        expect(w.findComponent(SelectInput).props("modelValue")).toBe("hard");
    });

    it("shows the empty message when there are no items", () => {
        const w = mountList({ items: [], selectedRows: [] });
        expect(w.text()).toContain("No workcenters have been set up yet.");
    });

    it("checking a row locally adds it as hard and reveals its mode select, emitting update:selectedRows", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(w.findAllComponents(CheckboxInput)[0].props("modelValue")).toBe(true);
        expect(w.findAllComponents(SelectInput)).toHaveLength(2);
        expect(w.emitted("update:selectedRows").at(-1)[0]).toEqual([
            { workcenter_id: 2, mode: "hard" },
            { workcenter_id: 1, mode: "hard" },
        ]);
    });

    it("unchecking a row locally clears it and hides its mode select", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[1].vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        expect(w.findAllComponents(SelectInput)).toHaveLength(0);
        expect(w.emitted("update:selectedRows").at(-1)[0]).toEqual([]);
    });

    it("changing the mode select updates that row's mode and emits update:selectedRows", async () => {
        const w = mountList();
        w.findComponent(SelectInput).vm.$emit("update:modelValue", "soft");
        await w.vm.$nextTick();

        expect(w.findComponent(SelectInput).props("modelValue")).toBe("soft");
        expect(w.emitted("update:selectedRows").at(-1)[0]).toEqual([{ workcenter_id: 2, mode: "soft" }]);
    });
});
