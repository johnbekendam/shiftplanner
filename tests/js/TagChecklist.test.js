import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "tags.empty": "No tags have been set up yet.",
};

const { router } = vi.hoisted(() => ({ router: { put: vi.fn(), delete: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
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
    mount(TagChecklist, {
        props: {
            items,
            selectedIds: [2],
            endpoint: "/employees/7/competences",
            emptyKey: "tags.empty",
            ...props,
        },
    });

beforeEach(() => {
    router.put.mockReset();
    router.delete.mockReset();
});

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

    it("attaches with a PUT when an item is checked on", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, data, opts] = router.put.mock.calls[0];
        expect(url).toBe("/employees/7/competences/1");
        expect(data).toEqual({});
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
        expect(router.delete).not.toHaveBeenCalled();
    });

    it("detaches with a DELETE when an item is checked off", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[1].vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        expect(router.delete).toHaveBeenCalledTimes(1);
        const [url, opts] = router.delete.mock.calls[0];
        expect(url).toBe("/employees/7/competences/2");
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
        expect(router.put).not.toHaveBeenCalled();
    });
});
