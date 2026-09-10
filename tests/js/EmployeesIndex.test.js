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
    "employees.action.delete_selected": "Delete selected (:count)",
    "employees.confirm.delete_selected": "Permanently delete :count selected employees and their dependent scheduling data?",
    "employees.hours_option": ":count hours",
    "employees.pagination.range": ":from-:to of :total",
    "employees.pagination.prev": "Previous",
    "employees.pagination.next": "Next",
    "employees.pagination.page": "Page :current of :total",
};

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn(), visit: vi.fn() },
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
    from: 1,
    to: 2,
    total: 2,
    prev_page_url: null,
    next_page_url: null,
    links: [
        { url: null, label: "&laquo; Previous", active: false },
        { url: "/employees?page=1", label: "1", active: true },
        { url: null, label: "Next &raquo;", active: false },
    ],
};

const mountIndex = (props = {}) =>
    mount(Index, {
        props: { employees, search: "", sort: "name", direction: "asc", ...props },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
    });

beforeEach(() => {
    vi.useFakeTimers();
    router.get.mockReset();
    router.post.mockReset();
    router.visit.mockReset();
    vi.stubGlobal("confirm", vi.fn());
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe("Employees/Index", () => {
    it("shows name, business line and weekly-hours columns", () => {
        const w = mountIndex();
        const headers = w.findAll("thead th").map((th) => th.text());

        expect(headers).toEqual(["", "Name", "Business line", "Weekly hours", ""]);
        expect(w.text()).not.toContain("Email");
    });

    it("renders the assigned business line, with a dash when there is none", () => {
        const w = mountIndex();
        const rows = w.findAll("tbody tr");

        expect(rows[0].findAll("td")[2].text()).toBe("PMP");
        expect(rows[1].findAll("td")[2].text()).toBe("—");
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

    it("renders current-page selection controls and a disabled danger action", () => {
        const w = mountIndex();
        const checkboxes = w.findAll('input[type="checkbox"]');
        const deleteButton = w.findAll("button").find((button) => button.text() === "Delete selected (0)");

        expect(checkboxes).toHaveLength(3);
        expect(deleteButton.attributes("disabled")).toBeDefined();
    });

    it("selects one employee without opening its row", async () => {
        const w = mountIndex();
        const checkboxes = w.findAll('input[type="checkbox"]');

        await checkboxes[1].setValue(true);

        const deleteButton = w.findAll("button").find((button) => button.text() === "Delete selected (1)");
        expect(deleteButton.attributes("disabled")).toBeUndefined();
        expect(router.visit).not.toHaveBeenCalled();
    });

    it("selects all visible employees from the header checkbox", async () => {
        const w = mountIndex();
        const checkboxes = w.findAll('input[type="checkbox"]');

        await checkboxes[0].setValue(true);

        expect(checkboxes[1].element.checked).toBe(true);
        expect(checkboxes[2].element.checked).toBe(true);
        expect(w.text()).toContain("Delete selected (2)");
    });

    it("clears selection when search or sort navigation starts", async () => {
        const w = mountIndex();
        await w.findAll('input[type="checkbox"]')[1].setValue(true);

        await w.findAll("thead th button")[1].trigger("click");
        expect(w.text()).toContain("Delete selected (0)");

        await w.findAll('input[type="checkbox"]')[1].setValue(true);
        await w.get('input[type="search"]').setValue("ann");
        vi.advanceTimersByTime(300);
        expect(w.text()).toContain("Delete selected (0)");
    });

    it("clears selection when page navigation starts", async () => {
        const w = mountIndex({
            employees: {
                ...employees,
                last_page: 2,
                next_page_url: "/employees?page=2",
                links: [
                    { url: null, label: "&laquo; Previous", active: false },
                    { url: "/employees?page=1", label: "1", active: true },
                    { url: "/employees?page=2", label: "2", active: false },
                    { url: "/employees?page=2", label: "Next &raquo;", active: false },
                ],
            },
        });
        await w.findAll('input[type="checkbox"]')[1].setValue(true);

        const nextButton = w.find('[aria-label="Next"]');
        await nextButton.trigger("click");

        expect(w.text()).toContain("Delete selected (0)");
        expect(router.get).toHaveBeenCalledWith(
            "/employees?page=2",
            {},
            { preserveState: true },
        );
    });

    it("uses the compact card-footer paginator from the theme-builder preview", async () => {
        const w = mountIndex({
            employees: {
                ...employees,
                current_page: 2,
                last_page: 3,
                from: 21,
                to: 40,
                total: 42,
                prev_page_url: "/employees?search=ann&page=1",
                next_page_url: "/employees?search=ann&page=3",
                links: [
                    { url: "/employees?search=ann&page=1", label: "&laquo; Previous", active: false },
                    { url: "/employees?search=ann&page=1", label: "1", active: false },
                    { url: "/employees?search=ann&page=2", label: "2", active: true },
                    { url: "/employees?search=ann&page=3", label: "3", active: false },
                    { url: "/employees?search=ann&page=3", label: "Next &raquo;", active: false },
                ],
            },
        });

        const paginator = w.get('[data-testid="employees-pagination"]');
        const controls = paginator.findAll("button");

        expect(paginator.text()).toContain("21-40 of 42");
        expect(controls.map((button) => button.text())).toEqual(["‹", "1", "2", "3", "›"]);
        expect(controls[2].classes()).toContain("font-semibold");

        await controls[3].trigger("click");

        expect(router.get).toHaveBeenCalledWith(
            "/employees?search=ann&page=3",
            {},
            { preserveState: true },
        );
    });

    it("confirms and submits only the selected employee ids", async () => {
        confirm.mockReturnValue(true);
        const w = mountIndex();
        await w.findAll('input[type="checkbox"]')[1].setValue(true);

        const deleteButton = w.findAll("button").find((button) => button.text() === "Delete selected (1)");
        await deleteButton.trigger("click");

        expect(confirm).toHaveBeenCalledWith(
            "Permanently delete 1 selected employees and their dependent scheduling data?",
        );
        expect(router.post).toHaveBeenCalledWith(
            "/employees/bulk-delete",
            { ids: [1] },
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
    });
});
