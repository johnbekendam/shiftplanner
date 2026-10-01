import { describe, it, expect, vi } from "vitest";
import { mount, DOMWrapper } from "@vue/test-utils";

const en = { "whats_new.title": "What's new", "whats_new.close": "Close" };

vi.mock("@inertiajs/vue3", () => ({ usePage: () => ({ props: { translations: en } }) }));

import WhatsNewDialog from "@/components/WhatsNewDialog.vue";

const body = () => new DOMWrapper(document.body);
const entries = [
    { id: "b", date: "2026-10-01", title: "Demand page", html: "<p>A <strong>new</strong> page.</p>" },
    { id: "a", date: "2026-09-01", title: "Older", html: "<p>Old.</p>" },
];

describe("WhatsNewDialog", () => {
    it("lists each entry with its title, date and rendered body", () => {
        const w = mount(WhatsNewDialog, { props: { open: true, entries }, attachTo: document.body });

        const items = body().findAll('[data-testid="whats-new-entry"]');
        expect(items).toHaveLength(2);
        expect(items[0].text()).toContain("Demand page");
        expect(items[0].text()).toContain("01-10-2026");
        expect(items[0].find("strong").text()).toBe("new");
        expect(body().text()).toContain("What's new");
        w.unmount();
    });

    it("renders nothing while closed", () => {
        const w = mount(WhatsNewDialog, { props: { open: false, entries }, attachTo: document.body });
        expect(body().find('[data-testid="whats-new-entry"]').exists()).toBe(false);
        w.unmount();
    });

    it("emits close from the Close button", async () => {
        const w = mount(WhatsNewDialog, { props: { open: true, entries }, attachTo: document.body });
        await body().findAll("button").find((b) => b.text() === "Close").trigger("click");
        expect(w.emitted("close")).toHaveLength(1);
        w.unmount();
    });
});
