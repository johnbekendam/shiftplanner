import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "employees.title": "Employees",
    "employees.search_placeholder": "Search name or email",
    "employees.empty": "No employees yet.",
    "employees.column.name": "Name",
    "employees.column.email": "Email",
    "employees.column.weekly_hours": "Weekly hours",
    "employees.action.new": "New employee",
    "employees.hours_option": ":count hours",
};

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), visit: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    usePage: () => ({ props: { translations: en } }),
}));

import Index from "@/pages/Employees/Index.vue";

const employees = {
    data: [
        { id: 1, name: "Ann Ant", email: "ann@example.com", weekly_hours: 24 },
        { id: 2, name: "Bo Bee", email: "bo@example.com", weekly_hours: 40 },
    ],
    last_page: 1,
    current_page: 1,
    prev_page_url: null,
    next_page_url: null,
};

const mountIndex = () =>
    mount(Index, {
        props: { employees, search: "" },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
    });

beforeEach(() => {
    vi.useFakeTimers();
    router.get.mockReset();
    router.visit.mockReset();
});

afterEach(() => {
    vi.useRealTimers();
});

describe("Employees/Index", () => {
    it("shows only name, email and weekly-hours columns", () => {
        const w = mountIndex();
        const headers = w.findAll("thead th").map((th) => th.text());

        expect(headers).toEqual(["Name", "Email", "Weekly hours"]);
        expect(w.text()).not.toContain("Personal link");
    });

    it("has no per-row action buttons", () => {
        const w = mountIndex();
        expect(w.find("tbody button").exists()).toBe(false);
        expect(w.find("tbody a").exists()).toBe(false);
    });

    it("searches live while typing, debounced", async () => {
        const w = mountIndex();
        await w.get('input[type="search"]').setValue("ann");

        expect(router.get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledTimes(1);
        const [url, params] = router.get.mock.calls[0];
        expect(url).toBe("/employees");
        expect(params).toEqual({ search: "ann" });
    });

    it("collapses rapid keystrokes into one request", async () => {
        const w = mountIndex();
        const input = w.get('input[type="search"]');
        await input.setValue("a");
        vi.advanceTimersByTime(100);
        await input.setValue("an");
        vi.advanceTimersByTime(100);
        await input.setValue("ann");
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get.mock.calls[0][1]).toEqual({ search: "ann" });
    });

    it("drops the search param when the box is cleared", async () => {
        const w = mountIndex();
        const input = w.get('input[type="search"]');
        await input.setValue("ann");
        vi.advanceTimersByTime(300);
        await input.setValue("");
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledTimes(2);
        expect(router.get.mock.calls[1]).toEqual([
            "/employees",
            {},
            expect.objectContaining({ replace: true }),
        ]);
    });

    it("opens the employee details when a row is clicked", async () => {
        const w = mountIndex();
        await w.findAll("tbody tr")[1].trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/employees/2/edit");
    });
});
