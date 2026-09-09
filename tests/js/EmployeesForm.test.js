import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.holidays.empty": "No holidays yet.",
    "availability.holidays.save_first": "Save the employee first, then add holidays.",
    "competences.tab": "Competences",
    "competences.save_first": "Save the employee first, then set competences.",
    "competences.checklist_empty": "No competences have been set up yet.",
    "employees.field.name": "Name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.field.business_line": "Business line",
    "employees.field.business_line_none": "None",
    "employees.hours_option": ":count hours",
    "employees.form.edit_title": "Edit employee",
    "employees.form.create_title": "Add employee",
    "employees.action.save": "Save",
    "employees.action.cancel": "Cancel",
};

const { router } = vi.hoisted(() => ({ router: { post: vi.fn(), delete: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) => reactive({ ...initial, errors: {}, processing: false, put: vi.fn(), post: vi.fn() }),
}));

import Form from "@/pages/Employees/Form.vue";
import EmployeeFields from "@/components/EmployeeFields.vue";
import HolidayList from "@/components/HolidayList.vue";
import AvailabilityGrid from "@/components/AvailabilityGrid.vue";
import ShiftNote from "@/components/ShiftNote.vue";
import TagChecklist from "@/components/TagChecklist.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

describe("Employees/Form", () => {
    it("shows Details and Availability tabs", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.text()).toContain("Details");
        expect(w.text()).toContain("Availability");
    });

    it("starts on Details and reveals Availability on tab click", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        const hidden = (sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

        expect(hidden('[data-testid="panel-details"]')).toBe(false);
        expect(hidden('[data-testid="panel-availability"]')).toBe(true);
        expect(w.findComponent(HolidayList).props("endpoint")).toBe("/employees/3/holidays");
        expect(w.findComponent(AvailabilityGrid).props("endpoint")).toBe("/employees/3/availability");

        const availabilityTab = w.findAll("button").find((b) => b.text() === "Availability");
        await availabilityTab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden('[data-testid="panel-details"]')).toBe(true);
        expect(hidden('[data-testid="panel-availability"]')).toBe(false);
    });

    it("renders the shift note at the top of the Availability tab when set", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                shiftNoteHtml: "<p>Allowances table here</p>",
            },
            global: { stubs },
        });

        const note = w.findComponent(ShiftNote);
        expect(note.exists()).toBe(true);
        expect(note.props("html")).toBe("<p>Allowances table here</p>");
        expect(w.get('[data-testid="panel-availability"]').text()).toContain("Allowances table here");
    });

    it("renders no shift note when the prop is absent", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.findComponent(ShiftNote).exists()).toBe(false);
    });

    it("on create, the Availability tab explains the employee must be saved first", () => {
        const w = mount(Form, {
            props: { employee: null, holidays: [] },
            global: { stubs },
        });

        expect(w.findComponent(HolidayList).exists()).toBe(false);
        expect(w.findComponent(AvailabilityGrid).exists()).toBe(false);
        expect(w.text()).toContain("Save the employee first");
    });

    it("shows a Competences tab with the competence checklist on the employee endpoint", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                competences: [{ id: 1, name: "Forklift" }],
                competenceIds: [1],
            },
            global: { stubs },
        });

        const tab = w.findAll("button").find((b) => b.text() === "Competences");
        await tab.trigger("click");
        await w.vm.$nextTick();

        const lists = w.findAllComponents(TagChecklist);
        const byEndpoint = Object.fromEntries(lists.map((l) => [l.props("endpoint"), l]));

        expect(byEndpoint["/employees/3/competences"].props("selectedIds")).toEqual([1]);
        expect(byEndpoint["/employees/3/product-groups"]).toBeUndefined();
    });

    it("forwards the business lines to EmployeeFields and preselects the employee's line", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24, business_line_id: 8 },
                businessLines: [
                    { id: 5, abbreviation: "PMP" },
                    { id: 8, abbreviation: "VLV" },
                ],
                holidays: [],
            },
            global: { stubs },
        });

        const fields = w.findComponent(EmployeeFields);
        expect(fields.props("businessLines")).toHaveLength(2);
        expect(fields.props("form").business_line_id).toBe(8);
    });

    it("on create, the Competences tab asks the employee to be saved first", () => {
        const w = mount(Form, {
            props: { employee: null, holidays: [] },
            global: { stubs },
        });

        expect(w.findComponent(TagChecklist).exists()).toBe(false);
        expect(w.get('[data-testid="panel-competences"]').text()).toContain(
            "Save the employee first, then set competences.",
        );
    });
});
