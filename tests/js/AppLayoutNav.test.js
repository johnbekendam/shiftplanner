import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "nav.employees": "Employees",
    "nav.users": "Users",
    "nav.settings": "Settings",
    "nav.theme_builder": "Theme Builder",
};

const state = vi.hoisted(() => ({ user: null }));

vi.mock("@inertiajs/vue3", () => ({
    router: { post: vi.fn() },
    usePage: () => ({ props: { translations: en, auth: { user: state.user } }, url: "/employees" }),
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

import AppLayout from "@/layouts/AppLayout.vue";

const stubs = {
    Layout: { template: "<div><slot name='sidebar' /><slot /></div>" },
    AppLogo: true,
    FlashMessage: true,
};

const navHrefs = (w) => w.findAll("nav a").map((a) => a.attributes("href"));

beforeEach(() => {
    state.user = null;
});

describe("AppLayout navigation", () => {
    it("shows only Employees to a manager", () => {
        state.user = { role: "manager" };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).toEqual(["/employees"]);
    });

    it("shows Users, Settings and Theme Builder to an admin", () => {
        state.user = { role: "admin" };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).toEqual(["/employees", "/users", "/settings", "/theme-builder"]);
    });
});
