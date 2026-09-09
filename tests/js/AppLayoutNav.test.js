import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "nav.dashboard": "Dashboard",
    "nav.employees": "Employees",
    "nav.my_details": "My details",
    "nav.account": "Account",
    "nav.mailbox": "Mailbox",
    "nav.import": "Import",
    "nav.users": "Users",
    "nav.settings": "Settings",
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
    it("shows the Dashboard and Employees to a manager", () => {
        state.user = { role: "manager" };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).toEqual(["/dashboard", "/employees"]);
    });

    it("shows Import, Mailbox, Users and Settings to an admin, but not Theme Builder", () => {
        state.user = { role: "admin" };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).toEqual(["/dashboard", "/employees", "/import", "/mailbox", "/users", "/settings"]);
    });

    it("hides Import from a manager", () => {
        state.user = { role: "manager" };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).not.toContain("/import");
    });

    it("adds a My details link when the account is linked to an employee", () => {
        state.user = { role: "manager", employee_id: 12 };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).toEqual(["/dashboard", "/employees", "/employees/12/edit"]);
    });
});
