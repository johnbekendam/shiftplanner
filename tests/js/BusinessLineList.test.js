import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "business_lines.abbreviation": "Abbreviation",
    "business_lines.description": "Description",
    "business_lines.target_fte": "Target FTE",
    "business_lines.add": "Add business line",
    "business_lines.add_abbreviation_placeholder": "ABBR",
    "business_lines.add_description_placeholder": "New business line",
    "business_lines.drag_handle": "Drag to reorder",
    "business_lines.delete": "Delete",
    "business_lines.list_empty": "No business lines yet.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import BusinessLineList from "@/components/BusinessLineList.vue";
import { TextInput, NumberInput } from "@/components/ui/Input";

const items = [
    { id: 1, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 3 },
    { id: 2, abbreviation: "VLV", description: "Valves", target_fte: 2, employee_count: 0 },
];

const mountList = (props = {}) => mount(BusinessLineList, { props: { items, ...props } });

describe("BusinessLineList", () => {
    it("renders a row per item and the three headers", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="business-line-row"]')).toHaveLength(2);
        expect(w.text()).toContain("Abbreviation");
        expect(w.text()).toContain("Description");
        expect(w.text()).toContain("Target FTE");
    });

    it("shows an empty state with no items", () => {
        expect(mountList({ items: [] }).text()).toContain("No business lines yet.");
    });

    it("edits a field locally and emits update:items, without a network call", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="business-line-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "PUM");
        await w.vm.$nextTick();

        const emitted = w.emitted("update:items");
        expect(emitted).toBeTruthy();
        expect(emitted.at(-1)[0][0]).toMatchObject({ id: 1, abbreviation: "PUM" });
    });

    it("adds a new row locally and emits update:items, without a network call", async () => {
        const w = mountList({ items: [] });
        const addRow = w.get('[data-testid="business-line-add-row"]');
        addRow.findComponent(TextInput).vm.$emit("update:modelValue", "SNS");
        addRow.findAllComponents(TextInput)[1].vm.$emit("update:modelValue", "Sensors");
        addRow.findComponent(NumberInput).vm.$emit("update:modelValue", 1.5);
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="business-line-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toMatchObject([
            { id: null, abbreviation: "SNS", description: "Sensors", target_fte: 1.5 },
        ]);
    });

    it("does not add a row with a blank abbreviation", async () => {
        const w = mountList({ items: [] });
        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="business-line-row"]')).toHaveLength(0);
        expect(w.emitted("update:items")).toBeUndefined();
    });

    it("removes a row locally and emits update:items, without a confirmation or network call", async () => {
        const w = mountList();
        await w.findAll('[data-testid="business-line-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(w.findAll('[data-testid="business-line-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toEqual([items[0]]);
    });

    it("reorders rows on drag-and-drop and emits update:items", async () => {
        const w = mountList();
        const rows = w.findAll('[data-testid="business-line-row"]');

        await rows[0].trigger("dragstart");
        await rows[1].trigger("drop");

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0].map((r) => r.id)).toEqual([2, 1]);
    });
});
