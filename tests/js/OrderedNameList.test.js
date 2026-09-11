import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "widgets.name": "Name",
    "widgets.add": "Add widget",
    "widgets.add_placeholder": "New widget",
    "widgets.move_up": "Move up",
    "widgets.move_down": "Move down",
    "widgets.delete": "Delete",
    "widgets.delete_confirm": "This widget is held by :count employee(s). Delete it?",
    "widgets.list_empty": "No widgets yet.",
};

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import OrderedNameList from "@/components/OrderedNameList.vue";
import { TextInput } from "@/components/ui/Input";

const items = [
    { id: 1, name: "Forklift", position: 1, holder_count: 3 },
    { id: 2, name: "Cleanroom", position: 2, holder_count: 0 },
    { id: 3, name: "First aid", position: 3, holder_count: 1 },
];

const mountList = (props = {}) =>
    mount(OrderedNameList, {
        props: { items, endpoint: "/settings/widgets", i18nPrefix: "widgets", ...props },
    });

const rowInputs = (w) =>
    w.findAll('[data-testid="ordered-name-row"]').map((r) => r.findComponent(TextInput));

beforeEach(() => {
    router.post.mockReset();
    router.put.mockReset();
    router.delete.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

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

    it("posts a new item to the endpoint", async () => {
        const w = mountList({ items: [] });
        w.findAllComponents(TextInput)[0].vm.$emit("update:modelValue", "Welding");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(router.post).toHaveBeenCalledTimes(1);
        const [url, payload, opts] = router.post.mock.calls[0];
        expect(url).toBe("/settings/widgets");
        expect(payload).toEqual({ name: "Welding" });
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
    });

    it("renames an item through the endpoint when its field commits", async () => {
        const w = mountList();
        rowInputs(w)[0].vm.$emit("update:modelValue", "Forklift licence");
        await w.vm.$nextTick();

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, payload] = router.put.mock.calls[0];
        expect(url).toBe("/settings/widgets/1");
        expect(payload).toEqual({ name: "Forklift licence" });
    });

    it("does not rename when the field commits an unchanged value", async () => {
        const w = mountList();
        rowInputs(w)[0].vm.$emit("update:modelValue", "Forklift");
        await w.vm.$nextTick();

        expect(router.put).not.toHaveBeenCalled();
    });

    it("moves a row up and down through the move endpoint", async () => {
        const w = mountList();
        await w.get('[aria-label="Move down"]').trigger("click"); // first row: only a down control
        expect(router.put.mock.calls[0][0]).toBe("/settings/widgets/1/move");
        expect(router.put.mock.calls[0][1]).toEqual({ direction: "down" });

        router.put.mockReset();
        const lastRow = w.findAll('[data-testid="ordered-name-row"]')[2];
        await lastRow.get('[aria-label="Move up"]').trigger("click");
        expect(router.put.mock.calls[0][0]).toBe("/settings/widgets/3/move");
        expect(router.put.mock.calls[0][1]).toEqual({ direction: "up" });
    });

    it("hides the up control on the first row and the down control on the last", () => {
        const w = mountList();
        const rows = w.findAll('[data-testid="ordered-name-row"]');
        expect(rows[0].find('[aria-label="Move up"]').exists()).toBe(false);
        expect(rows[0].find('[aria-label="Move down"]').exists()).toBe(true);
        expect(rows[2].find('[aria-label="Move up"]').exists()).toBe(true);
        expect(rows[2].find('[aria-label="Move down"]').exists()).toBe(false);
    });

    it("asks for confirmation before deleting and names the holder count", async () => {
        const confirmSpy = vi.fn(() => false);
        vi.stubGlobal("confirm", confirmSpy);

        const w = mountList();
        await w.findAll('[data-testid="ordered-name-row"]')[0].get('[aria-label="Delete"]').trigger("click");

        expect(confirmSpy).toHaveBeenCalledWith("This widget is held by 3 employee(s). Delete it?");
        expect(router.delete).not.toHaveBeenCalled();
    });

    it("deletes through the endpoint once confirmed", async () => {
        vi.stubGlobal("confirm", vi.fn(() => true));

        const w = mountList();
        await w.findAll('[data-testid="ordered-name-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(router.delete).toHaveBeenCalledTimes(1);
        const [url, opts] = router.delete.mock.calls[0];
        expect(url).toBe("/settings/widgets/2");
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
    });
});
