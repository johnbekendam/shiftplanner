import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

vi.mock("@inertiajs/vue3", () => ({
    router: { post: vi.fn() },
    usePage: () => ({ props: { translations: {}, auth: { user: null }, appName: "ShiftPlanner" }, url: "/employees" }),
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

import AppLayout from "@/layouts/AppLayout.vue";
import CenteredLayout from "@/layouts/CenteredLayout.vue";

const stubs = {
    Layout: { template: "<div><slot name='topbar' /><slot name='sidebar' /><main data-testid='main'><slot /></main></div>" },
    AppLogo: true,
    FlashMessage: true,
};

describe("AppLayout fitHeight", () => {
    it("pads the page content and lets it grow by default", () => {
        const w = mount(AppLayout, { slots: { default: "<p>Page</p>" }, global: { stubs } });
        const content = w.get('[data-testid="page-content"]');

        expect(content.classes()).toContain("p-6");
        expect(content.classes()).not.toContain("min-h-0");
    });

    it("makes the page content a column that fills the page area", () => {
        const w = mount(AppLayout, { props: { fitHeight: true }, slots: { default: "<p>Page</p>" }, global: { stubs } });

        expect(w.get('[data-testid="page-frame"]').classes()).toEqual(expect.arrayContaining(["flex", "h-full", "flex-col"]));
        expect(w.get('[data-testid="page-content"]').classes()).toEqual(expect.arrayContaining(["flex", "min-h-0", "flex-1", "flex-col", "p-6"]));
    });
});

describe("CenteredLayout fitHeight", () => {
    it("keeps the card growing with its content by default, with no footer", () => {
        const w = mount(CenteredLayout, { slots: { default: "<p>Body</p>" }, global: { stubs } });

        expect(w.get('[data-testid="centered-body"]').classes()).not.toContain("overflow-y-auto");
        expect(w.find('[data-testid="centered-footer"]').exists()).toBe(false);
    });

    it("caps the card to the page area, scrolls its body and shows the footer slot", () => {
        const w = mount(CenteredLayout, {
            props: { fitHeight: true, align: "top" },
            slots: { header: "<nav>Tabs</nav>", default: "<p>Body</p>", footer: "<button>Save</button>" },
            global: { stubs },
        });

        expect(w.get('[data-testid="centered-frame"]').classes()).toEqual(expect.arrayContaining(["h-full"]));
        expect(w.get('[data-testid="centered-card"]').classes()).toEqual(expect.arrayContaining(["flex", "max-h-full", "min-h-0", "flex-col"]));
        const body = w.get('[data-testid="centered-body"]');
        expect(body.classes()).toEqual(expect.arrayContaining(["min-h-0", "flex-1", "overflow-y-auto"]));
        expect(w.get('[data-testid="centered-footer"]').text()).toBe("Save");
    });

    it("scrolls the body back to the top when scrollKey changes", async () => {
        const w = mount(CenteredLayout, {
            props: { fitHeight: true, scrollKey: "details" },
            slots: { default: "<p>Body</p>" },
            global: { stubs },
        });
        const body = w.get('[data-testid="centered-body"]').element;
        body.scrollTop = 250;

        await w.setProps({ scrollKey: "availability" });

        expect(body.scrollTop).toBe(0);
    });
});
