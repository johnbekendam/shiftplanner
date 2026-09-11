import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "business_lines.abbreviation": "Abbreviation",
    "business_lines.description": "Description",
    "business_lines.target_fte": "Target FTE",
    "business_lines.add": "Add business line",
    "business_lines.add_abbreviation_placeholder": "ABBR",
    "business_lines.add_description_placeholder": "New business line",
    "business_lines.move_up": "Move up",
    "business_lines.move_down": "Move down",
    "business_lines.delete": "Delete",
    "business_lines.delete_confirm": "This business line has :count employee(s). Delete it?",
    "business_lines.list_empty": "No business lines yet.",
};

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import BusinessLineList from "@/components/BusinessLineList.vue";
import { TextInput, NumberInput } from "@/components/ui/Input";

const items = [
    { id: 1, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 3 },
    { id: 2, abbreviation: "VLV", description: "Valves", target_fte: 2, employee_count: 0 },
];

const mountList = (props = {}) =>
    mount(BusinessLineList, {
        props: { items, endpoint: "/settings/business-lines", ...props },
    });

beforeEach(() => {
    router.post.mockReset();
    router.put.mockReset();
    router.delete.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

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

    it("writes the whole row through the endpoint when a field commits", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="business-line-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "PUM");
        await w.vm.$nextTick();

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, payload] = router.put.mock.calls[0];
        expect(url).toBe("/settings/business-lines/1");
        expect(payload).toEqual({ abbreviation: "PUM", description: "Pumps", target_fte: 4 });
    });

    it("does not write when a field commits an unchanged value", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="business-line-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "PMP");
        await w.vm.$nextTick();

        expect(router.put).not.toHaveBeenCalled();
    });

    it("posts a new business line from the add row", async () => {
        const w = mountList({ items: [] });
        const addRow = w.get('[data-testid="business-line-add-row"]');
        addRow.findComponent(TextInput).vm.$emit("update:modelValue", "SNS");
        addRow.findAllComponents(TextInput)[1].vm.$emit("update:modelValue", "Sensors");
        addRow.findComponent(NumberInput).vm.$emit("update:modelValue", 1.5);
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(router.post).toHaveBeenCalledTimes(1);
        const [url, payload] = router.post.mock.calls[0];
        expect(url).toBe("/settings/business-lines");
        expect(payload).toEqual({ abbreviation: "SNS", description: "Sensors", target_fte: 1.5 });
    });

    it("moves a row through the move endpoint", async () => {
        const w = mountList();
        await w.findAll('[data-testid="business-line-row"]')[0].get('[aria-label="Move down"]').trigger("click");
        expect(router.put.mock.calls[0][0]).toBe("/settings/business-lines/1/move");
        expect(router.put.mock.calls[0][1]).toEqual({ direction: "down" });
    });

    it("asks for confirmation naming the employee count before deleting", async () => {
        const confirmSpy = vi.fn(() => false);
        vi.stubGlobal("confirm", confirmSpy);

        const w = mountList();
        await w.findAll('[data-testid="business-line-row"]')[0].get('[aria-label="Delete"]').trigger("click");

        expect(confirmSpy).toHaveBeenCalledWith("This business line has 3 employee(s). Delete it?");
        expect(router.delete).not.toHaveBeenCalled();
    });

    it("deletes through the endpoint once confirmed", async () => {
        vi.stubGlobal("confirm", vi.fn(() => true));

        const w = mountList();
        await w.findAll('[data-testid="business-line-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(router.delete).toHaveBeenCalledTimes(1);
        expect(router.delete.mock.calls[0][0]).toBe("/settings/business-lines/2");
    });
});
