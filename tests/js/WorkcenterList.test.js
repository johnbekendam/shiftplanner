import { describe, it, expect, vi, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "workcenters.name": "Name",
    "workcenters.responsible": "Contact",
    "workcenters.add_responsible_placeholder": "Contact person",
    "workcenters.add": "Add workcenter",
    "workcenters.add_name_placeholder": "New workcenter",
    "workcenters.drag_handle": "Drag to reorder",
    "workcenters.delete": "Delete",
    "workcenters.list_empty": "No workcenters yet.",
    "workcenters.live_screen": "Live screen",
    "workcenters.live_copy": "Copy link",
    "workcenters.live_copied": "Link copied",
    "workcenters.live_regenerate": "Regenerate link",
    "workcenters.live_regenerate_title": "Regenerate the live-screen link?",
    "workcenters.live_regenerate_body": "The old link stops working at once.",
    "workcenters.live_regenerate_confirm": "Regenerate",
    "app.cancel": "Cancel",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import WorkcenterList from "@/components/WorkcenterList.vue";
import { TextInput } from "@/components/ui/Input";

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
        expect(w.text()).toContain("Contact");
    });

    it("edits the responsible name locally and emits update:items", async () => {
        const w = mountList({
            items: [{ id: 1, name: "Line 1", responsible: "Jane Doe", position: 1, archived_at: null, shifts: [] }],
        });
        const input = w.findAllComponents(TextInput)[1];
        expect(input.props("modelValue")).toBe("Jane Doe");

        input.vm.$emit("update:modelValue", "John Smith");
        await w.vm.$nextTick();

        expect(w.emitted("update:items").at(-1)[0][0]).toMatchObject({ id: 1, responsible: "John Smith" });
    });

    it("adds a new row with a responsible name", async () => {
        const w = mountList({ items: [] });
        const [name, responsible] = w.get('[data-testid="workcenter-add-row"]').findAllComponents(TextInput);
        name.vm.$emit("update:modelValue", "Line 3");
        responsible.vm.$emit("update:modelValue", "Jane Doe");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(w.emitted("update:items").at(-1)[0]).toMatchObject([
            { id: null, name: "Line 3", responsible: "Jane Doe" },
        ]);
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

    it("hides the bin button once a row has an attached shift", () => {
        const w = mountList({
            items: [{ id: 1, name: "Line 1", position: 1, archived_at: null, shifts: [{ id: 9, name: "Early" }] }],
        });
        const row = w.findAll('[data-testid="workcenter-row"]')[0];

        expect(row.find('[aria-label="Delete"]').exists()).toBe(false);
    });

    it("shows the bin button for a row with no attached shift", () => {
        const w = mountList();
        const row = w.findAll('[data-testid="workcenter-row"]')[0];

        expect(row.find('[aria-label="Delete"]').exists()).toBe(true);
    });

    describe("live screen link", () => {
        const liveUrls = { 1: "https://app.test/live/aaa", 2: "https://app.test/live/bbb" };
        const mountLive = (props = {}) => mount(WorkcenterList, {
            props: { items, liveUrls, ...props },
            global: { stubs: { teleport: true } },
        });

        afterEach(() => vi.unstubAllGlobals());

        it("shows the copy and regenerate buttons on each saved row, without an open link", () => {
            const w = mountLive();

            expect(w.find('[data-testid="workcenter-live-copy-2"]').exists()).toBe(true);
            expect(w.find('[data-testid="workcenter-live-regenerate-2"]').exists()).toBe(true);
            expect(w.find("a").exists()).toBe(false);
        });

        it("shows no live link on a row that is not saved yet", async () => {
            const w = mountLive({ items: [] });
            w.get('[data-testid="workcenter-add-row"]').findComponent(TextInput).vm.$emit("update:modelValue", "Line 3");
            await w.get("form").trigger("submit");

            const row = w.get('[data-testid="workcenter-row"]');
            expect(row.find('[aria-label="Copy link"]').exists()).toBe(false);
        });

        it("shows no live link on an archived row, because the link no longer works", () => {
            const w = mountLive({
                items: [{ id: 1, name: "Line 1", position: 1, archived_at: "2026-09-01T00:00:00Z", shifts: [{ id: 9, name: "Early" }] }],
            });

            expect(w.find('[data-testid="workcenter-live-copy-1"]').exists()).toBe(false);
        });

        it("copies the link and confirms it", async () => {
            const writeText = vi.fn().mockResolvedValue();
            vi.stubGlobal("navigator", { clipboard: { writeText } });
            const w = mountLive();

            await w.get('[data-testid="workcenter-live-copy-1"]').trigger("click");
            await w.vm.$nextTick();

            expect(writeText).toHaveBeenCalledWith("https://app.test/live/aaa");
            expect(w.get('[data-testid="workcenter-live-copy-1"]').attributes("aria-label")).toBe("Link copied");
        });

        it("does not fail when the clipboard is not available", async () => {
            vi.stubGlobal("navigator", {});
            const w = mountLive();

            await w.get('[data-testid="workcenter-live-copy-1"]').trigger("click");

            expect(w.get('[data-testid="workcenter-live-copy-1"]').attributes("aria-label")).toBe("Copy link");
        });

        it("asks for confirmation before it regenerates, and emits only after confirm", async () => {
            const w = mountLive();

            await w.get('[data-testid="workcenter-live-regenerate-2"]').trigger("click");
            expect(w.text()).toContain("Regenerate the live-screen link?");
            expect(w.emitted("regenerate-live-link")).toBeUndefined();

            await w.findComponent({ name: "ConfirmDialog" }).vm.$emit("confirm");

            expect(w.emitted("regenerate-live-link")).toEqual([[2]]);
            expect(w.text()).not.toContain("Regenerate the live-screen link?");
        });

        it("emits nothing when the confirmation is cancelled", async () => {
            const w = mountLive();

            await w.get('[data-testid="workcenter-live-regenerate-1"]').trigger("click");
            await w.findComponent({ name: "ConfirmDialog" }).vm.$emit("cancel");

            expect(w.emitted("regenerate-live-link")).toBeUndefined();
            expect(w.text()).not.toContain("Regenerate the live-screen link?");
        });
    });
});
