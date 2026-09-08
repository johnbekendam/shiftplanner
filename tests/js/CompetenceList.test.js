import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "competences.name": "Name",
    "competences.add": "Add competence",
    "competences.add_placeholder": "New competence",
    "competences.move_up": "Move up",
    "competences.move_down": "Move down",
    "competences.delete": "Delete",
    "competences.delete_confirm": "This competence is held by :count employee(s). Delete it?",
    "competences.list_empty": "No competences yet.",
};

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import CompetenceList from "@/components/CompetenceList.vue";
import { TextInput } from "@/components/ui/Input";

const competences = [
    { id: 1, name: "Forklift", position: 1, holder_count: 3 },
    { id: 2, name: "Cleanroom", position: 2, holder_count: 0 },
    { id: 3, name: "First aid", position: 3, holder_count: 1 },
];

const mountList = (props = {}) =>
    mount(CompetenceList, {
        props: { competences, endpoint: "/settings/competences", ...props },
    });

const rowInputs = (w) =>
    w.findAll('[data-testid="competence-row"]').map((r) => r.findComponent(TextInput));

beforeEach(() => {
    router.post.mockReset();
    router.put.mockReset();
    router.delete.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe("CompetenceList", () => {
    it("renders a row per competence", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="competence-row"]')).toHaveLength(3);
        expect(w.text()).toContain("Name");
    });

    it("shows an empty state with no competences", () => {
        const w = mountList({ competences: [] });
        expect(w.text()).toContain("No competences yet.");
    });

    it("posts a new competence to the endpoint", async () => {
        const w = mountList({ competences: [] });
        w.findAllComponents(TextInput)[0].vm.$emit("update:modelValue", "Welding");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(router.post).toHaveBeenCalledTimes(1);
        const [url, payload, opts] = router.post.mock.calls[0];
        expect(url).toBe("/settings/competences");
        expect(payload).toEqual({ name: "Welding" });
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
    });

    it("renames a competence through the endpoint when its field commits", async () => {
        const w = mountList();
        rowInputs(w)[0].vm.$emit("update:modelValue", "Forklift licence");
        await w.vm.$nextTick();

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, payload] = router.put.mock.calls[0];
        expect(url).toBe("/settings/competences/1");
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
        expect(router.put.mock.calls[0][0]).toBe("/settings/competences/1/move");
        expect(router.put.mock.calls[0][1]).toEqual({ direction: "down" });

        router.put.mockReset();
        const lastRow = w.findAll('[data-testid="competence-row"]')[2];
        await lastRow.get('[aria-label="Move up"]').trigger("click");
        expect(router.put.mock.calls[0][0]).toBe("/settings/competences/3/move");
        expect(router.put.mock.calls[0][1]).toEqual({ direction: "up" });
    });

    it("hides the up control on the first row and the down control on the last", () => {
        const w = mountList();
        const rows = w.findAll('[data-testid="competence-row"]');
        expect(rows[0].find('[aria-label="Move up"]').exists()).toBe(false);
        expect(rows[0].find('[aria-label="Move down"]').exists()).toBe(true);
        expect(rows[2].find('[aria-label="Move up"]').exists()).toBe(true);
        expect(rows[2].find('[aria-label="Move down"]').exists()).toBe(false);
    });

    it("asks for confirmation before deleting and names the holder count", async () => {
        const confirmSpy = vi.fn(() => false);
        vi.stubGlobal("confirm", confirmSpy);

        const w = mountList();
        await w.findAll('[data-testid="competence-row"]')[0].get('[aria-label="Delete"]').trigger("click");

        expect(confirmSpy).toHaveBeenCalledWith("This competence is held by 3 employee(s). Delete it?");
        expect(router.delete).not.toHaveBeenCalled();
    });

    it("deletes through the endpoint once confirmed", async () => {
        vi.stubGlobal("confirm", vi.fn(() => true));

        const w = mountList();
        await w.findAll('[data-testid="competence-row"]')[1].get('[aria-label="Delete"]').trigger("click");

        expect(router.delete).toHaveBeenCalledTimes(1);
        const [url, opts] = router.delete.mock.calls[0];
        expect(url).toBe("/settings/competences/2");
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
    });
});
