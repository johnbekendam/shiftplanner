import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "workcenters.name": "Name",
    "workcenters.add": "Add workcenter",
    "workcenters.add_name_placeholder": "New workcenter",
    "workcenters.drag_handle": "Drag to reorder",
    "workcenters.archived": "Archived",
    "workcenters.delete": "Delete",
    "workcenters.list_empty": "No workcenters yet.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import WorkcenterList from "@/components/WorkcenterList.vue";
import { TextInput, CheckboxInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Line 1", position: 1, archived_at: null, shifts: [] },
    { id: 2, name: "Line 2", position: 2, archived_at: null, shifts: [] },
];

const mountList = (props = {}) => mount(WorkcenterList, { props: { items, ...props } });

describe("WorkcenterList", () => {
    it("renders a row per item and the header", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="workcenter-row"]')).toHaveLength(2);
        expect(w.text()).toContain("Name");
    });

    it("shows an empty state with no items", () => {
        expect(mountList({ items: [] }).text()).toContain("No workcenters yet.");
    });

    it("edits a field locally and emits update:items, without a network call", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="workcenter-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "Line 1A");
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted).toBeTruthy();
        expect(emitted.at(-1)[0][0]).toMatchObject({ id: 1, name: "Line 1A" });
    });

    it("adds a new row locally and emits update:items, without a network call", async () => {
        const w = mountList({ items: [] });
        const addRow = w.get('[data-testid="workcenter-add-row"]');
        addRow.findComponent(TextInput).vm.$emit("update:modelValue", "Line 3");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="workcenter-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toMatchObject([
            { id: null, name: "Line 3", shifts: [] },
        ]);
    });

    it("does not add a row with a blank name", async () => {
        const w = mountList({ items: [] });
        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="workcenter-row"]')).toHaveLength(0);
        expect(w.emitted("update:items")).toBeUndefined();
    });

    it("removes a row locally and emits update:items, without a confirmation or network call", async () => {
        const w = mountList();
        await w.findAll('[data-testid="workcenter-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(w.findAll('[data-testid="workcenter-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toEqual([items[0]]);
    });

    it("reorders rows on drag-and-drop and emits update:items", async () => {
        const w = mountList();
        const rows = w.findAll('[data-testid="workcenter-row"]');

        await rows[0].trigger("dragstart");
        await rows[1].trigger("dragover");

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0].map((r) => r.id)).toEqual([2, 1]);
    });

    it("hides the bin button and shows the Archived checkbox once a row has an attached shift", () => {
        const w = mountList({
            items: [{ id: 1, name: "Line 1", position: 1, archived_at: null, shifts: [{ id: 9, name: "Early" }] }],
        });
        const row = w.findAll('[data-testid="workcenter-row"]')[0];

        expect(row.find('[aria-label="Delete"]').exists()).toBe(false);
        expect(row.findComponent(CheckboxInput).exists()).toBe(true);
    });

    it("shows the bin button and no Archived checkbox for a row with no attached shift", () => {
        const w = mountList();
        const row = w.findAll('[data-testid="workcenter-row"]')[0];

        expect(row.find('[aria-label="Delete"]').exists()).toBe(true);
        expect(row.findComponent(CheckboxInput).exists()).toBe(false);
    });

    it("toggling Archived changes local state and emits, without a network call", async () => {
        const w = mountList({
            items: [{ id: 1, name: "Line 1", position: 1, archived_at: null, shifts: [{ id: 9, name: "Early" }] }],
        });
        const row = w.findAll('[data-testid="workcenter-row"]')[0];
        row.findComponent(CheckboxInput).vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0][0].archived_at).not.toBeNull();
    });
});
