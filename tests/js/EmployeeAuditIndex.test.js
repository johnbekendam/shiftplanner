import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "audit.title": "Employee audit log",
    "audit.search_employee": "Search employees",
    "audit.search_actor": "Search actors",
    "audit.filter.action": "Action",
    "audit.filter.source": "Source",
    "audit.filter.from": "From",
    "audit.filter.to": "To",
    "audit.filter.all_actions": "All actions",
    "audit.filter.all_sources": "All sources",
    "audit.column.when": "When",
    "audit.column.employee": "Employee",
    "audit.column.action": "Action",
    "audit.column.actor": "Actor",
    "audit.column.source": "Source",
    "audit.empty": "No audit events found.",
    "audit.pagination.range": ":from-:to of :total",
    "audit.pagination.prev": "Previous",
    "audit.pagination.next": "Next",
    "audit.pagination.page_label": "Page :page",
    "employees.audit.system_actor": "System",
    "employees.audit.before": "Before",
    "employees.audit.after": "After",
    "employees.audit.not_set": "Not set",
    "employees.audit.action.updated": "Employee updated",
    "employees.audit.source.user": "Manager or administrator",
};

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    router: { get },
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, auth: { user: { role: "admin" }, settings: { date_format: "ymd" } } } }),
}));

import Index from "@/pages/EmployeeAudit/Index.vue";
import { DateInput, SearchInput, SelectInput } from "@/components/ui/Input";

const event = {
    id: 1,
    employee_id: 3,
    employee_name: "Ada Lovelace",
    action: "updated",
    source: "user",
    actor_name: "Admin User",
    actor_email: "admin@example.com",
    old_values: { weekly_hours: 24 },
    new_values: { weekly_hours: 28 },
    created_at: "2026-09-24T10:00:00+00:00",
};

function mountPage(overrides = {}) {
    return mount(Index, {
        props: {
            events: {
                data: [event],
                from: 1,
                to: 1,
                total: 1,
                links: [
                    { url: null, label: "Previous", active: false },
                    { url: "/employee-audit?page=1", label: "1", active: true },
                    { url: "/employee-audit?page=2", label: "Next", active: false },
                ],
            },
            filters: { employee: "", actor: "", action: null, source: null, from: null, to: null },
            actions: ["updated"],
            sources: ["user"],
            ...overrides,
        },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" }, teleport: true } },
    });
}

beforeEach(() => {
    get.mockReset();
    vi.useFakeTimers();
});

describe("EmployeeAudit/Index", () => {
    it("renders and expands event changes", async () => {
        const wrapper = mountPage();
        const row = wrapper.get('[data-testid="audit-row-1"]');

        expect(row.text()).toContain("Ada Lovelace");
        expect(row.text()).toContain("Employee updated");
        expect(row.text()).toContain("Admin User");
        expect(wrapper.find('[data-testid="audit-details-1"]').exists()).toBe(false);

        await row.get("button").trigger("click");
        expect(wrapper.get('[data-testid="audit-details-1"]').text()).toContain("weekly_hours");
        expect(wrapper.get('[data-testid="audit-details-1"]').text()).toContain("24");
        expect(wrapper.get('[data-testid="audit-details-1"]').text()).toContain("28");
    });

    it("reloads with search and typed filters", async () => {
        const wrapper = mountPage();
        const searches = wrapper.findAllComponents(SearchInput);

        await searches[0].setValue("Ada");
        vi.advanceTimersByTime(250);
        expect(get).toHaveBeenLastCalledWith("/employee-audit", { employee: "Ada" }, expect.any(Object));

        wrapper.findAllComponents(SelectInput)[0].vm.$emit("update:modelValue", "updated");
        await wrapper.vm.$nextTick();
        expect(get).toHaveBeenLastCalledWith("/employee-audit", { employee: "Ada", action: "updated" }, expect.any(Object));

        wrapper.findAllComponents(DateInput)[0].vm.$emit("update:modelValue", "2026-09-01");
        await wrapper.vm.$nextTick();
        expect(get).toHaveBeenLastCalledWith("/employee-audit", { employee: "Ada", action: "updated", from: "2026-09-01" }, expect.any(Object));
    });

    it("uses server pagination links", async () => {
        const wrapper = mountPage();

        await wrapper.get('[aria-label="Next"]').trigger("click");

        expect(get).toHaveBeenCalledWith("/employee-audit?page=2", {}, { preserveState: true });
    });
});
