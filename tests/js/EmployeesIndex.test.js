import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "employees.title": "Employees",
    "employees.search_placeholder": "Search name or email",
    "employees.empty": "No employees yet.",
    "employees.column.name": "Name",
    "employees.column.business_line": "Business line",
    "employees.column.weekly_hours": "Weekly hours",
    "employees.column.flexibility": "Flexibility",
    "employees.no_business_line": "—",
    "employees.action.new": "New employee",
    "employees.action.send_link": "Send link",
    "employees.action.sending_link": "Sending…",
    "employees.action.link_sent": "Sent",
    "employees.action.delete_selected": "Delete selected",
    "employees.confirm.delete_selected": "Permanently delete :count selected employees and their dependent scheduling data?",
    "employees.hours_option": ":count hours",
    "employees.pagination.range": ":from-:to of :total",
    "employees.pagination.prev": "Previous",
    "employees.pagination.next": "Next",
    "employees.pagination.page": "Page :current of :total",
};

const state = vi.hoisted(() => ({ user: { role: "admin" } }));
const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn(), visit: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { translations: en, auth: { user: state.user } } }),
}));

import Index from "@/pages/Employees/Index.vue";

const employees = {
    data: [
        {
            id: 1,
            name: "Ann Ant",
            business_line: "PMP",
            weekly_hours: 24,
            shift_coverage: [
                { shift_id: 1, name: "Morning", coverage_percentage: 100 },
                { shift_id: 2, name: "Evening", coverage_percentage: 60 },
            ],
        },
        {
            id: 2,
            name: "Bo Bee",
            business_line: null,
            weekly_hours: 40,
            shift_coverage: [
                { shift_id: 1, name: "Morning", coverage_percentage: 20 },
                { shift_id: 2, name: "Evening", coverage_percentage: 100 },
            ],
        },
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

const deleteButton = (w, count) => w.get(`[aria-label="Delete selected ${count}"]`);

beforeEach(() => {
    vi.useFakeTimers();
    state.user = { role: "admin" };
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

        expect(headers).toEqual(["", "Name", "Business line", "Weekly hours", "Flexibility", ""]);
        expect(w.text()).not.toContain("Email");
    });

    it("renders shift coverage percentages for each employee", () => {
        const w = mountIndex();
        const rows = w.findAll("tbody tr");

        expect(rows[0].findAll("td")[4].text()).toContain("Morning 100%");
        expect(rows[0].findAll("td")[4].text()).toContain("Evening 60%");
        expect(rows[1].findAll("td")[4].text()).toContain("Morning 20%");
        expect(rows[1].findAll("td")[4].text()).toContain("Evening 100%");
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

    it("shows Send link for every row and posts straight to the outbox", async () => {
        const w = mountIndex();
        const rows = w.findAll("tbody tr");

        const first = rows[0].find("td:last-child button");
        expect(first.text()).toBe("Send link");
        expect(rows[1].find("td:last-child button").text()).toBe("Send link");

        await first.trigger("click");

        expect(router.post).toHaveBeenCalledWith(
            "/employees/1/send-link",
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it("shows in-button feedback while sending and briefly after success", async () => {
        const w = mountIndex();
        const button = () => w.find("tbody tr td:last-child button");

        await button().trigger("click");
        expect(button().text()).toBe("Sending…");
        expect(button().attributes("disabled")).toBeDefined();

        const opts = router.post.mock.calls[0][2];
        opts.onSuccess();
        opts.onFinish();
        await w.vm.$nextTick();
        expect(button().text()).toBe("Sent");
        expect(button().attributes("disabled")).toBeUndefined();

        vi.advanceTimersByTime(2000);
        await w.vm.$nextTick();
        expect(button().text()).toBe("Send link");
    });

    it("also shows the send-link action for a manager", () => {
        state.user = { role: "manager" };
        const w = mountIndex();

        expect(w.find("tbody tr td:last-child button").text()).toBe("Send link");
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

        expect(checkboxes).toHaveLength(3);
        expect(deleteButton(w, 0).attributes("disabled")).toBeDefined();
    });

    it("selects one employee without opening its row", async () => {
        const w = mountIndex();
        const checkboxes = w.findAll('input[type="checkbox"]');

        await checkboxes[1].setValue(true);

        expect(deleteButton(w, 1).attributes("disabled")).toBeUndefined();
        expect(router.visit).not.toHaveBeenCalled();
    });

    it("selects all visible employees from the header checkbox", async () => {
        const w = mountIndex();
        const checkboxes = w.findAll('input[type="checkbox"]');

        await checkboxes[0].setValue(true);

        expect(checkboxes[1].element.checked).toBe(true);
        expect(checkboxes[2].element.checked).toBe(true);
        expect(deleteButton(w, 2).attributes("disabled")).toBeUndefined();
    });

    it("clears selection when search or sort navigation starts", async () => {
        const w = mountIndex();
        await w.findAll('input[type="checkbox"]')[1].setValue(true);

        await w.findAll("thead th button")[1].trigger("click");
        expect(deleteButton(w, 0).attributes("disabled")).toBeDefined();

        await w.findAll('input[type="checkbox"]')[1].setValue(true);
        await w.get('input[type="search"]').setValue("ann");
        vi.advanceTimersByTime(300);
        expect(deleteButton(w, 0).attributes("disabled")).toBeDefined();
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

        expect(deleteButton(w, 0).attributes("disabled")).toBeDefined();
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

        await deleteButton(w, 1).trigger("click");

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
