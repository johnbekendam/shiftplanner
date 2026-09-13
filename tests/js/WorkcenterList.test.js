import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "workcenters.name": "Name",
    "workcenters.description": "Description",
    "workcenters.add": "Add workcenter",
    "workcenters.add_name_placeholder": "New workcenter",
    "workcenters.add_description_placeholder": "New workcenter",
    "workcenters.drag_handle": "Drag to reorder",
    "workcenters.archived": "Archived",
    "workcenters.delete": "Delete",
    "workcenters.list_empty": "No workcenters yet.",
    "workcenters.shifts.expand": "Show shifts",
    "workcenters.shifts.attach": "Shifts",
    "workcenters.shifts.weekday.mon": "Mon",
    "workcenters.shifts.weekday.tue": "Tue",
    "workcenters.shifts.weekday.wed": "Wed",
    "workcenters.shifts.weekday.thu": "Thu",
    "workcenters.shifts.weekday.fri": "Fri",
    "workcenters.shifts.weekday.sat": "Sat",
    "workcenters.shifts.weekday.sun": "Sun",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import WorkcenterList from "@/components/WorkcenterList.vue";
import { TextInput, CheckboxInput, NumberInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Line 1", description: "Assembly", position: 1, archived_at: null, shifts: [] },
    { id: 2, name: "Line 2", description: "Packaging", position: 2, archived_at: null, shifts: [] },
];

const mountList = (props = {}) => mount(WorkcenterList, { props: { items, ...props } });

describe("WorkcenterList", () => {
    it("renders a row per item and the two headers", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="workcenter-row"]')).toHaveLength(2);
        expect(w.text()).toContain("Name");
        expect(w.text()).toContain("Description");
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
        addRow.findAllComponents(TextInput)[1].vm.$emit("update:modelValue", "New line");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="workcenter-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toMatchObject([
            { id: null, name: "Line 3", description: "New line", shifts: [] },
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
            items: [{ id: 1, name: "Line 1", description: "", position: 1, archived_at: null, shifts: [{ id: 9, name: "Early" }] }],
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
            items: [{ id: 1, name: "Line 1", description: "", position: 1, archived_at: null, shifts: [{ id: 9, name: "Early" }] }],
        });
        const row = w.findAll('[data-testid="workcenter-row"]')[0];
        row.findComponent(CheckboxInput).vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0][0].archived_at).not.toBeNull();
    });

    const allShifts = [{ id: 9, name: "Early", start_time: "06:00", end_time: "14:00" }];

    it("has no expand control for a not-yet-saved row", () => {
        const w = mountList({ items: [] });
        const addRow = w.get('[data-testid="workcenter-add-row"]');
        addRow.findComponent(TextInput).vm.$emit("update:modelValue", "Line 3");

        expect(w.find('[data-testid^="workcenter-expand-"]').exists()).toBe(false);
    });

    it("expanding a row shows its shift checkboxes and capacity grid, firing no request", async () => {
        const w = mountList({
            items: [{ id: 1, name: "Line 1", description: "", position: 1, archived_at: null, shifts: [{ id: 9, name: "Early", weekday_capacities: [1, 1, 1, 1, 1, 0, 0] }] }],
            allShifts,
        });

        expect(w.find('[data-testid="workcenter-detail-1"]').exists()).toBe(false);
        await w.get('[data-testid="workcenter-expand-1"]').trigger("click");

        const detail = w.get('[data-testid="workcenter-detail-1"]');
        expect(detail.findComponent(CheckboxInput).exists()).toBe(true);
        expect(detail.findAllComponents(NumberInput)).toHaveLength(7);
    });

    it("toggling a shift attaches or detaches it locally and emits, firing no request", async () => {
        const w = mountList({
            items: [{ id: 1, name: "Line 1", description: "", position: 1, archived_at: null, shifts: [] }],
            allShifts,
        });
        await w.get('[data-testid="workcenter-expand-1"]').trigger("click");

        const detail = w.get('[data-testid="workcenter-detail-1"]');
        detail.findComponent(CheckboxInput).vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0][0].shifts).toMatchObject([{ id: 9, name: "Early" }]);
    });

    it("editing a capacity cell changes local state and emits, firing no request", async () => {
        const w = mountList({
            items: [{ id: 1, name: "Line 1", description: "", position: 1, archived_at: null, shifts: [{ id: 9, name: "Early", weekday_capacities: [0, 0, 0, 0, 0, 0, 0] }] }],
            allShifts,
        });
        await w.get('[data-testid="workcenter-expand-1"]').trigger("click");

        const detail = w.get('[data-testid="workcenter-detail-1"]');
        detail.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 4);
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0][0].shifts[0].weekday_capacities).toEqual([4, 0, 0, 0, 0, 0, 0]);
    });
});
