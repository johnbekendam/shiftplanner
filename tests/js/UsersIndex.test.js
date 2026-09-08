import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "users.title": "Users",
    "users.action.new": "Add user",
    "users.column.name": "Name",
    "users.column.email": "Email",
    "users.column.role": "Role",
    "users.column.status": "Status",
    "users.role.admin": "Admin",
    "users.role.manager": "Manager",
    "users.status.active": "Active",
    "users.status.inactive": "Inactive",
    "users.empty": "No users yet.",
};

const { router } = vi.hoisted(() => ({ router: { visit: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
    usePage: () => ({ props: { translations: en } }),
}));

import Index from "@/pages/Users/Index.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const users = [
    { id: 1, name: "Dana Admin", email: "dana@example.com", role: "admin", is_active: true },
    { id: 2, name: "Mel Manager", email: "mel@example.com", role: "manager", is_active: false },
];

describe("Users/Index", () => {
    it("renders a row per user with role and status", () => {
        const w = mount(Index, { props: { users }, global: { stubs } });
        const rows = w.findAll('[data-testid="user-row"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain("Admin");
        expect(rows[1].text()).toContain("Inactive");
    });

    it("opens the editor on row click", async () => {
        const w = mount(Index, { props: { users }, global: { stubs } });
        await w.findAll('[data-testid="user-row"]')[1].trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/users/2/edit");
    });

    it("shows an empty state", () => {
        const w = mount(Index, { props: { users: [] }, global: { stubs } });
        expect(w.text()).toContain("No users yet.");
    });
});
