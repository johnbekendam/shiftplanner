import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "shifts.name": "Name",
    "shifts.start_time": "Start",
    "shifts.end_time": "End",
    "shifts.add": "Add shift",
    "shifts.add_name_placeholder": "New shift",
    "shifts.delete": "Delete",
    "shifts.list_empty": "No shifts yet.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import ShiftList from "@/components/ShiftList.vue";
import { TextInput, TimeInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Early", start_time: "06:00", end_time: "14:00" },
    { id: 2, name: "Late", start_time: "14:00", end_time: "22:00" },
];

const mountList = (props = {}) => mount(ShiftList, { props: { items, ...props } });

describe("ShiftList", () => {
    it("renders a row per item and the three headers", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="shift-row"]')).toHaveLength(2);
        expect(w.text()).toContain("Name");
        expect(w.text()).toContain("Start");
        expect(w.text()).toContain("End");
    });

    it("shows an empty state with no items", () => {
        expect(mountList({ items: [] }).text()).toContain("No shifts yet.");
    });

    it("edits a field locally and emits update:items, without a network call", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="shift-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "Early bird");
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0][0]).toMatchObject({ id: 1, name: "Early bird" });
    });

    it("adds a new row locally and emits update:items, without a network call", async () => {
        const w = mountList({ items: [] });
        const addRow = w.get('[data-testid="shift-add-row"]');
        addRow.findComponent(TextInput).vm.$emit("update:modelValue", "Night");
        const times = addRow.findAllComponents(TimeInput);
        times[0].vm.$emit("update:modelValue", "22:00");
        times[1].vm.$emit("update:modelValue", "23:30");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="shift-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toMatchObject([
            { id: null, name: "Night", start_time: "22:00", end_time: "23:30" },
        ]);
    });

    it("does not add a row with a blank name", async () => {
        const w = mountList({ items: [] });
        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="shift-row"]')).toHaveLength(0);
        expect(w.emitted("update:items")).toBeUndefined();
    });

    it("removes a row locally and emits update:items, without a confirmation or network call", async () => {
        const w = mountList();
        await w.findAll('[data-testid="shift-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(w.findAll('[data-testid="shift-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toEqual([items[0]]);
    });
});
