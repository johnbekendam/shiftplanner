import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "shifts.name": "Name",
    "shifts.start_time": "Start",
    "shifts.end_time": "End",
    "shifts.add": "Add shift",
    "shifts.add_name_placeholder": "New shift",
    "shifts.delete": "Delete",
    "shifts.delete_confirm": "Delete this shift? Any availability set for it will be lost.",
    "shifts.list_empty": "No shifts yet.",
};

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import ShiftList from "@/components/ShiftList.vue";
import { TextInput, TimeInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Early", start_time: "06:00", end_time: "14:00" },
    { id: 2, name: "Late", start_time: "14:00", end_time: "22:00" },
];

const mountList = (props = {}) =>
    mount(ShiftList, {
        props: { items, endpoint: "/settings/shifts", ...props },
    });

beforeEach(() => {
    router.post.mockReset();
    router.put.mockReset();
    router.delete.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

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

    it("writes the whole row through the endpoint when a field commits", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="shift-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "Early bird");
        await w.vm.$nextTick();

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, payload] = router.put.mock.calls[0];
        expect(url).toBe("/settings/shifts/1");
        expect(payload).toEqual({ name: "Early bird", start_time: "06:00", end_time: "14:00" });
    });

    it("briefly highlights the row once the write succeeds", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="shift-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "Early bird");
        await w.vm.$nextTick();

        router.put.mock.calls[0][2].onSuccess();
        await w.vm.$nextTick();

        expect(firstRow.classes()).toContain("bg-(--color-badge-success-bg)");
    });

    it("does not write when a field commits an unchanged value", async () => {
        const w = mountList();
        const firstRow = w.findAll('[data-testid="shift-row"]')[0];
        firstRow.findComponent(TextInput).vm.$emit("update:modelValue", "Early");
        await w.vm.$nextTick();

        expect(router.put).not.toHaveBeenCalled();
    });

    it("posts a new shift from the add row", async () => {
        const w = mountList({ items: [] });
        const addRow = w.get('[data-testid="shift-add-row"]');
        addRow.findComponent(TextInput).vm.$emit("update:modelValue", "Night");
        const times = addRow.findAllComponents(TimeInput);
        times[0].vm.$emit("update:modelValue", "22:00");
        times[1].vm.$emit("update:modelValue", "23:30");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(router.post).toHaveBeenCalledTimes(1);
        const [url, payload] = router.post.mock.calls[0];
        expect(url).toBe("/settings/shifts");
        expect(payload).toEqual({ name: "Night", start_time: "22:00", end_time: "23:30" });
    });

    it("asks for a plain confirmation before deleting", async () => {
        const confirmSpy = vi.fn(() => false);
        vi.stubGlobal("confirm", confirmSpy);

        const w = mountList();
        await w.findAll('[data-testid="shift-row"]')[0].get('[aria-label="Delete"]').trigger("click");

        expect(confirmSpy).toHaveBeenCalledWith(
            "Delete this shift? Any availability set for it will be lost.",
        );
        expect(router.delete).not.toHaveBeenCalled();
    });

    it("deletes through the endpoint once confirmed", async () => {
        vi.stubGlobal("confirm", vi.fn(() => true));

        const w = mountList();
        await w.findAll('[data-testid="shift-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(router.delete).toHaveBeenCalledTimes(1);
        expect(router.delete.mock.calls[0][0]).toBe("/settings/shifts/2");
    });
});
