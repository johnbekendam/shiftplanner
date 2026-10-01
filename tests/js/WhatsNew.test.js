import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "whats_new.title": "What's new",
    "whats_new.new": "New",
    "whats_new.previous": "Previous",
    "whats_new.next": "Next",
    "whats_new.empty": "No changes yet.",
};

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import WhatsNew from "@/pages/WhatsNew.vue";

const ENTRIES = [
    { id: "c", date: "2026-10-01", title: "Newest", html: "<p>C body</p>", new: true },
    { id: "b", date: "2026-09-01", title: "Middle", html: "<p>B body</p>", new: false },
    { id: "a", date: "2026-08-01", title: "Oldest", html: "<p>A body</p>", new: false },
];

const mountPage = (props = {}) =>
    mount(WhatsNew, {
        props: { entries: ENTRIES, selectedId: "c", ...props },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
    });

const items = (w) => w.findAll('[data-testid="whats-new-item"]');
const shown = (w) => w.get('[data-testid="whats-new-entry"]');
const button = (w, label) => w.findAll("button").find((b) => b.text() === label);

describe("WhatsNew page", () => {
    it("lists every entry with its date and a New label on the unseen ones", () => {
        const w = mountPage();
        expect(items(w).map((i) => i.text())).toEqual([
            expect.stringContaining("Newest"),
            expect.stringContaining("Middle"),
            expect.stringContaining("Oldest"),
        ]);
        expect(items(w)[0].text()).toContain("01-10-2026");
        expect(items(w)[0].find('[data-testid="whats-new-new"]').exists()).toBe(true);
        expect(items(w)[1].find('[data-testid="whats-new-new"]').exists()).toBe(false);
    });

    it("shows the selected entry", () => {
        const w = mountPage({ selectedId: "b" });
        expect(shown(w).text()).toContain("Middle");
        expect(shown(w).text()).toContain("B body");
    });

    it("shows another entry when it is picked in the list", async () => {
        const w = mountPage();
        await items(w)[2].trigger("click");
        expect(shown(w).text()).toContain("Oldest");
    });

    it("steps to the older entry with Previous and to the newer one with Next", async () => {
        const w = mountPage({ selectedId: "b" });

        await button(w, "Previous").trigger("click");
        expect(shown(w).text()).toContain("Oldest");
        expect(button(w, "Previous").attributes("disabled")).toBeDefined();

        await button(w, "Next").trigger("click");
        await button(w, "Next").trigger("click");
        expect(shown(w).text()).toContain("Newest");
        expect(button(w, "Next").attributes("disabled")).toBeDefined();
    });

    it("shows an empty state without entries", () => {
        const w = mountPage({ entries: [], selectedId: null });
        expect(w.text()).toContain("No changes yet.");
        expect(w.find('[data-testid="whats-new-entry"]').exists()).toBe(false);
    });
});
