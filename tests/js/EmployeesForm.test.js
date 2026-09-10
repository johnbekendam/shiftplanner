import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "availability.tab.information": "Information",
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.info.empty": "No information has been provided yet.",
    "availability.info.cta": "Please update your details, availability and competences on the different tabs.",
    "availability.hours_warning.not_preferred": "You will be planned on not-preferred hours.",
    "availability.hours_warning.insufficient": "Your available time totals :available hours per week, below your target of :target hours.",
    "availability.holidays.empty": "No holidays yet.",
    "availability.holidays.save_first": "Save the employee first, then add holidays.",
    "availability.questions.heading": "Questions",
    "competences.tab": "Competences",
    "competences.save_first": "Save the employee first, then set competences.",
    "competences.checklist_empty": "No competences have been set up yet.",
    "employees.field.first_name": "First name",
    "employees.field.last_name": "Last name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.field.business_line": "Business line",
    "employees.field.business_line_none": "None",
    "employees.hours_option": ":count hours",
    "employees.form.edit_title": "Edit employee",
    "employees.form.create_title": "Add employee",
    "employees.action.save": "Save",
    "employees.action.create": "Create",
    "employees.action.cancel": "Cancel",
    "employees.action.send_link": "Send link",
    "employees.action.resend_link": "Resend link",
};

const { router } = vi.hoisted(() => ({ router: { post: vi.fn(), delete: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) => reactive({ ...initial, errors: {}, processing: false, isDirty: false, put: vi.fn(), post: vi.fn() }),
}));

import Form from "@/pages/Employees/Form.vue";
import EmployeeFields from "@/components/EmployeeFields.vue";
import WeeklyHoursField from "@/components/WeeklyHoursField.vue";
import HolidayList from "@/components/HolidayList.vue";
import AvailabilityGrid from "@/components/AvailabilityGrid.vue";
import ShiftNote from "@/components/ShiftNote.vue";
import TagChecklist from "@/components/TagChecklist.vue";
import QuestionChecklist from "@/components/QuestionChecklist.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

describe("Employees/Form", () => {
    it("shows Information, Details and Availability tabs", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.text()).toContain("Information");
        expect(w.text()).toContain("Details");
        expect(w.text()).toContain("Availability");
    });

    it("starts on Information and reveals Availability on tab click", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        const hidden = (sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

        expect(hidden('[data-testid="panel-information"]')).toBe(false);
        expect(hidden('[data-testid="panel-availability"]')).toBe(true);
        expect(w.findComponent(HolidayList).props("endpoint")).toBe("/employees/3/holidays");
        expect(w.findComponent(AvailabilityGrid).props("endpoint")).toBe("/employees/3/availability");

        const availabilityTab = w.findAll("button").find((b) => b.text() === "Availability");
        await availabilityTab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden('[data-testid="panel-information"]')).toBe(true);
        expect(hidden('[data-testid="panel-availability"]')).toBe(false);
    });

    it("renders the shift note on the Information tab when set", () => {
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
        expect(w.get('[data-testid="panel-information"]').text()).toContain("Allowances table here");
    });

    it("shows an empty state on the Information tab when no shift note is set", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.findComponent(ShiftNote).exists()).toBe(false);
        expect(w.get('[data-testid="panel-information"]').text()).toContain("No information has been provided yet.");
    });

    it("Information tab prompts the employee to update the other tabs", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        expect(w.get('[data-testid="panel-information"]').text()).toContain(
            "Please update your details, availability and competences on the different tabs.",
        );
    });

    it("on create, shows only the Details fields with a Create button and no tabs", () => {
        const w = mount(Form, {
            props: { employee: null, holidays: [] },
            global: { stubs },
        });

        expect(w.findComponent(EmployeeFields).exists()).toBe(true);
        expect(w.get('[data-testid="panel-details"]').text()).toContain("Create");
        expect(w.findComponent(EmployeeFields).props("form").weekly_hours).toBe(32);

        // No tab bar, no other panels.
        expect(w.findAll("button").some((b) => b.text() === "Availability")).toBe(false);
        expect(w.find('[data-testid="panel-information"]').exists()).toBe(false);
        expect(w.find('[data-testid="panel-availability"]').exists()).toBe(false);
        expect(w.find('[data-testid="panel-competences"]').exists()).toBe(false);
        expect(w.findComponent(HolidayList).exists()).toBe(false);
        expect(w.findComponent(AvailabilityGrid).exists()).toBe(false);
    });

    it("shows the questions checklist on the Availability tab, pointed at the employee endpoint", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                questions: [{ id: 5, text: "Can we contact you to work in the weekend?" }],
                questionAnswers: [5],
            },
            global: { stubs },
        });

        const checklist = w.findComponent(QuestionChecklist);
        expect(checklist.exists()).toBe(true);
        expect(checklist.props("endpoint")).toBe("/employees/3/questions");
        expect(checklist.props("answeredIds")).toEqual([5]);
        expect(w.get('[data-testid="panel-availability"]').text()).toContain("Questions");
    });

    it("omits the questions section when no question is configured", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.findComponent(QuestionChecklist).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').text()).not.toContain("Questions");
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

    it("does not show a personal-link action on the Details tab", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24, link_sent: false },
                holidays: [],
            },
            global: { stubs },
        });

        expect(w.get('[data-testid="panel-details"]').text()).not.toContain("Send link");
    });

    it("on create, submitting posts to the employees endpoint", async () => {
        const w = mount(Form, {
            props: { employee: null, holidays: [] },
            global: { stubs },
        });

        expect(w.findComponent(TagChecklist).exists()).toBe(false);

        const form = w.findComponent(EmployeeFields).props("form");
        await w.get('[data-testid="panel-details"] form').trigger("submit");
        expect(form.post).toHaveBeenCalledWith("/employees");
    });

    it("puts weekly hours on the Availability tab, not on Details", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        expect(w.get('[data-testid="panel-details"]').findComponent(WeeklyHoursField).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField).exists()).toBe(true);
    });

    it("shows no availability-hours warning when preferred hours meet the target", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 20 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                holidays: [],
            },
            global: { stubs },
        });

        expect(w.find('[data-testid="availability-hours-warning"]').exists()).toBe(false);
    });

    it("warns when the target requires not-preferred hours", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 20 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                availability: [{ weekday: 1, shift_id: 1, level: "not_preferred" }],
                holidays: [],
            },
            global: { stubs },
        });

        expect(w.get('[data-testid="availability-hours-warning"]').text())
            .toContain("You will be planned on not-preferred hours.");
    });

    it("warns when all available hours are below the target", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 20 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                availability: [
                    { weekday: 1, shift_id: 1, level: "unavailable" },
                    { weekday: 2, shift_id: 1, level: "unavailable" },
                ],
                holidays: [],
            },
            global: { stubs },
        });

        expect(w.get('[data-testid="availability-hours-warning"]').text())
            .toContain("Your available time totals 12 hours per week, below your target of 20 hours.");
    });

    it("updates the availability-hours warning after an availability-grid change", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 20 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                holidays: [],
            },
            global: { stubs },
        });

        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", {
            weekday: 1,
            shiftId: 1,
            level: "unavailable",
        });
        await w.vm.$nextTick();

        expect(w.get('[data-testid="availability-hours-warning"]').text())
            .toContain("Your available time totals 16 hours per week, below your target of 20 hours.");
    });

    it("auto-saves when weekly hours changes on the Availability tab", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        const hours = w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField);
        const form = w.findComponent(EmployeeFields).props("form");
        hours.vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(form.weekly_hours).toBe(40);
        expect(form.put).toHaveBeenCalled();
        expect(form.put.mock.calls[0][0]).toBe("/employees/3");
    });

    it("auto-saves a Details field when it changes, and keeps the Save button", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        const form = w.findComponent(EmployeeFields).props("form");

        form.email = "new@b.c";
        await w.vm.$nextTick();

        expect(form.put).toHaveBeenCalled();
        expect(form.put.mock.calls[0][0]).toBe("/employees/3");
        expect(w.get('[data-testid="panel-details"]').text()).toContain("Save");
    });

    it("auto-saves on leaving the Details tab with unsaved changes", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        const form = w.findComponent(EmployeeFields).props("form");
        form.isDirty = true;

        // Start on Details, then move away.
        const detailsTab = w.findAll("button").find((b) => b.text() === "Details");
        await detailsTab.trigger("click");
        await w.vm.$nextTick();
        const availabilityTab = w.findAll("button").find((b) => b.text() === "Availability");
        await availabilityTab.trigger("click");
        await w.vm.$nextTick();

        expect(form.put).toHaveBeenCalled();
    });

    it("does not auto-save on create", async () => {
        const w = mount(Form, { props: { employee: null, holidays: [] }, global: { stubs } });
        const form = w.findComponent(EmployeeFields).props("form");

        form.email = "x@y.z";
        await w.vm.$nextTick();

        expect(form.put).not.toHaveBeenCalled();
    });
});
