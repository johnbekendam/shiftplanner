import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "nav.employees": "Employees",
    "nav.settings": "Settings",
    "nav.theme_builder": "Theme Builder",
};

vi.mock("@inertiajs/vue3", () => ({
    router: { post: vi.fn() },
    usePage: () => ({ props: { translations: en, auth: { user: null } }, url: "/employees" }),
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

import AppLayout from "@/layouts/AppLayout.vue";

const stubs = {
    Layout: { template: "<div><slot name='sidebar' /><slot /></div>" },
    AppLogo: true,
    FlashMessage: true,
};

describe("AppLayout navigation", () => {
    it("lists a Settings item linking to /settings", () => {
        const w = mount(AppLayout, { global: { stubs } });
        const settings = w.findAll("a").find((a) => a.text().includes("Settings"));

        expect(settings).toBeTruthy();
        expect(settings.attributes("href")).toBe("/settings");
    });
});
