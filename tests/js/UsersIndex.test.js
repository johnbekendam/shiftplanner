import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "users.title": "Users",
    "users.search_placeholder": "Search name or email",
    "users.action.new": "Add user",
    "users.column.name": "Name",
    "users.column.email": "Email",
    "users.column.business_line": "Business line",
    "users.column.role": "Role",
    "users.column.status": "Status",
    "users.role.admin": "Admin",
    "users.role.manager": "Manager",
    "users.status.active": "Active",
    "users.status.inactive": "Inactive",
    "users.no_business_line": "—",
    "users.empty": "No users yet.",
    "users.action.resend_invite": "Resend invite",
    "users.action.resending_invite": "Sending…",
    "users.action.invite_sent": "Sent",
};

const state = vi.hoisted(() => ({ user: { role: "admin" } }));
const { router } = vi.hoisted(() => ({ router: { get: vi.fn(), visit: vi.fn(), post: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
    usePage: () => ({ props: { translations: en, auth: { user: state.user } } }),
}));

import Index from "@/pages/Users/Index.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountIndex = (props = {}) => mount(Index, { props: { users, search: "", ...props }, global: { stubs } });

const users = [
    {
        id: 1,
        name: "Dana Admin",
        email: "dana@example.com",
        business_line: "PMP",
        role: "admin",
        is_active: true,
        has_password: true,
    },
    {
        id: 2,
        name: "Mel Manager",
        email: "mel@example.com",
        business_line: null,
        role: "manager",
        is_active: false,
        has_password: false,
    },
];

describe("Users/Index", () => {
    beforeEach(() => {
        state.user = { role: "admin" };
        router.get.mockClear();
        router.visit.mockClear();
        router.post.mockClear();
    });

    it("renders a row per user with role and status", () => {
        const w = mountIndex();
        const rows = w.findAll('[data-testid="user-row"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain("Admin");
        expect(rows[1].text()).toContain("Inactive");
    });

    it("renders each user's business line abbreviation or an empty marker", () => {
        const w = mountIndex();
        const rows = w.findAll('[data-testid="user-row"]');

        expect(w.text()).toContain("Business line");
        expect(rows[0].text()).toContain("PMP");
        expect(rows[1].text()).toContain("—");
    });

    it("renders a search field seeded from the search prop", () => {
        const w = mountIndex({ search: "mel" });
        const input = w.get('input[type="search"]');

        expect(input.attributes("placeholder")).toBe("Search name or email");
        expect(input.element.value).toBe("mel");
    });

    it("searches live while typing, debounced", async () => {
        vi.useFakeTimers();
        const w = mountIndex();

        await w.get('input[type="search"]').setValue("mel");

        expect(router.get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledWith(
            "/users",
            { search: "mel" },
            expect.objectContaining({ preserveState: true, replace: true }),
        );
        vi.useRealTimers();
    });

    it("drops the search param when the box is cleared", async () => {
        vi.useFakeTimers();
        const w = mountIndex({ search: "mel" });

        await w.get('input[type="search"]').setValue("");
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledWith(
            "/users",
            {},
            expect.objectContaining({ preserveState: true, replace: true }),
        );
        vi.useRealTimers();
    });

    it("opens the editor on row click", async () => {
        const w = mountIndex();
        await w.findAll('[data-testid="user-row"]')[1].trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/users/2/edit");
    });

    it("shows an empty state", () => {
        const w = mountIndex({ users: [] });
        expect(w.text()).toContain("No users yet.");
    });

    it("shows Resend invite for every row regardless of password status", () => {
        const w = mountIndex();
        const rows = w.findAll('[data-testid="user-row"]');

        expect(rows[0].find('[data-testid="resend-invite"]').exists()).toBe(true);
        expect(rows[1].find('[data-testid="resend-invite"]').exists()).toBe(true);
    });

    it("posts a resend without opening the editor", async () => {
        const w = mountIndex();
        await w.findAll('[data-testid="user-row"]')[1].find('[data-testid="resend-invite"]').trigger("click");

        expect(router.post).toHaveBeenCalledWith(
            "/users/2/resend-invite",
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(router.visit).not.toHaveBeenCalled();
    });

    it("shows in-button feedback while sending and briefly after success", async () => {
        vi.useFakeTimers();
        const w = mountIndex();
        const button = () => w.find('[data-testid="resend-invite"]');

        await button().trigger("click");
        expect(button().text()).toBe("Sending…");
        expect(button().attributes("disabled")).toBeDefined();

        // Inertia always calls onFinish right after onSuccess/onError.
        const opts = router.post.mock.calls[0][2];
        opts.onSuccess();
        opts.onFinish();
        await w.vm.$nextTick();
        expect(button().text()).toBe("Sent");
        expect(button().attributes("disabled")).toBeUndefined();

        vi.advanceTimersByTime(2000);
        await w.vm.$nextTick();
        expect(button().text()).toBe("Resend invite");

        vi.useRealTimers();
    });

    it("shows Add user for an admin", () => {
        const w = mountIndex();
        expect(w.findAll("a").some((a) => a.attributes("href") === "/users/create")).toBe(true);
    });

    it("hides Add user and Resend invite for a manager, but still opens rows", async () => {
        state.user = { role: "manager" };
        const w = mountIndex();

        expect(w.findAll("a").some((a) => a.attributes("href") === "/users/create")).toBe(false);
        expect(w.find('[data-testid="resend-invite"]').exists()).toBe(false);

        await w.findAll('[data-testid="user-row"]')[1].trigger("click");
        expect(router.visit).toHaveBeenCalledWith("/users/2/edit");
    });
});
