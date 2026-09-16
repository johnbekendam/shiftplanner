import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "nav.dashboard": "Dashboard",
    "nav.employees": "Employees",
    "nav.my_details": "My details",
    "nav.account": "Account",
    "nav.mailbox": "Mailbox",
    "nav.workcenter_shifts": "Schedule",
    "nav.scheduling": "Planning",
    "nav.planning_rules": "Planning rules",
    "nav.employee_backup": "Backup",
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
const navLabels = (w) => w.findAll("nav a").map((a) => a.text());

beforeEach(() => {
    state.user = null;
});

describe("AppLayout navigation", () => {
    it("shows Dashboard, Employees and the read-only Users to a manager", () => {
        state.user = { role: "manager" };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).toEqual(["/dashboard", "/employees", "/users"]);
    });

    it("shows Backup, Mailbox, Schedule, Planning, Planning rules, Users and Settings to an admin, but not Theme Builder", () => {
        state.user = { role: "admin" };
        const w = mount(AppLayout, { global: { stubs } });
        const hrefs = navHrefs(w);

        expect(hrefs).toEqual([
            "/dashboard",
            "/employees",
            "/schedule",
            "/planning",
            "/planning-rules",
            "/mailbox",
            "/users",
            "/settings",
            "/employee-backup",
        ]);
        expect(navLabels(w)).toContain("Backup");
    });

    it("hides Backup from a manager", () => {
        state.user = { role: "manager" };
        const w = mount(AppLayout, { global: { stubs } });
        const hrefs = navHrefs(w);

        expect(hrefs).not.toContain("/employee-backup");
        expect(navLabels(w)).not.toContain("Backup");
    });

    it("adds a My details link when the account is linked to an employee", () => {
        state.user = { role: "manager", employee_id: 12 };
        const hrefs = navHrefs(mount(AppLayout, { global: { stubs } }));

        expect(hrefs).toEqual(["/dashboard", "/employees", "/users", "/employees/12/edit"]);
    });
});
