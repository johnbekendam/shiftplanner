import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "employees.title": "Employees",
    "employees.search_placeholder": "Search name or email",
    "employees.empty": "No employees yet.",
    "employees.column.name": "Name",
    "employees.column.business_line": "Business line",
    "employees.column.weekly_hours": "Weekly hours",
    "employees.no_business_line": "—",
    "employees.action.new": "New employee",
    "employees.action.send_link": "Send link",
    "employees.action.resend_link": "Resend link",
    "employees.hours_option": ":count hours",
};

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), visit: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { translations: en } }),
}));

import Index from "@/pages/Employees/Index.vue";

const employees = {
    data: [
        { id: 1, name: "Ann Ant", business_line: "PMP", weekly_hours: 24, link_sent: false },
        { id: 2, name: "Bo Bee", business_line: null, weekly_hours: 40, link_sent: true },
    ],
    last_page: 1,
    current_page: 1,
    prev_page_url: null,
    next_page_url: null,
};

const mountIndex = (props = {}) =>
    mount(Index, {
        props: { employees, search: "", sort: "name", direction: "asc", ...props },
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
    it("shows name, business line and weekly-hours columns", () => {
        const w = mountIndex();
        const headers = w.findAll("thead th").map((th) => th.text());

        expect(headers).toEqual(["Name", "Business line", "Weekly hours", ""]);
        expect(w.text()).not.toContain("Email");
    });

    it("renders the assigned business line, with a dash when there is none", () => {
        const w = mountIndex();
        const rows = w.findAll("tbody tr");

        expect(rows[0].findAll("td")[1].text()).toBe("PMP");
        expect(rows[1].findAll("td")[1].text()).toBe("—");
    });

    it("sorts by a column when its header is clicked", async () => {
        const w = mountIndex();
        await w.findAll("thead th button")[1].trigger("click"); // Business line

        expect(router.get).toHaveBeenCalledTimes(1);
        const [url, params] = router.get.mock.calls[0];
        expect(url).toBe("/employees");
        expect(params).toEqual({ sort: "business_line", direction: undefined });
    });

    it("toggles to descending when the active column header is clicked again", async () => {
        const w = mountIndex({ sort: "business_line", direction: "asc" });
        await w.findAll("thead th button")[1].trigger("click");

        expect(router.get.mock.calls[0][1]).toEqual({ sort: "business_line", direction: "desc" });
    });

    it("clears the sort back to the default when the name header cycles off", async () => {
        const w = mountIndex({ sort: "name", direction: "asc" });
        await w.findAll("thead th button")[0].trigger("click");

        expect(router.get.mock.calls[0][1]).toEqual({ sort: undefined, direction: "desc" });
    });

    it("shows a Send link / Resend link action per row pointing at compose", () => {
        const w = mountIndex();
        const rows = w.findAll("tbody tr");

        const first = rows[0].find("td:last-child a");
        expect(first.text()).toBe("Send link");
        expect(first.attributes("href")).toBe(
            "/mailbox?tab=compose&type=personal_page_link&employee=1",
        );

        expect(rows[1].find("td:last-child a").text()).toBe("Resend link");
    });

    it("does not open the employee when the row action is clicked", async () => {
        const w = mountIndex();
        await w.findAll("tbody tr")[0].find("td:last-child").trigger("click");

        expect(router.visit).not.toHaveBeenCalled();
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
