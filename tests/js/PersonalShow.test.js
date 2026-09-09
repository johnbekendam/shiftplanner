import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.field.first_name": "First name",
    "employees.field.last_name": "Last name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.field.business_line": "Business line",
    "employees.field.business_line_none": "None",
    "employees.hours_option": ":count hours",
    "employees.hours_below_minimum": "I can only work less than :min hours",
    "personal.title": "Your working hours",
    "personal.action.save": "Save",
    "personal.saved": "Saved",
    "personal.locked_notice": "Changes are currently closed by your planner.",
    "availability.tab.information": "Information",
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.info.empty": "No information has been provided yet.",
    "availability.info.cta": "Please update your details, availability and competences on the different tabs.",
    "availability.holidays.empty": "No holidays yet.",
    "availability.questions.heading": "Questions",
    "competences.tab": "Competences",
    "competences.checklist_empty": "No competences have been set up yet.",
};

const { router } = vi.hoisted(() => ({ router: { post: vi.fn(), delete: vi.fn() } }));

const form = reactive({
    first_name: "",
    last_name: "",
    email: "",
    weekly_hours: null,
    business_line_id: null,
    errors: {},
    processing: false,
    recentlySuccessful: false,
    isDirty: false,
    _transform: null,
    transform(fn) {
        this._transform = fn;
        return this;
    },
    put(url, opts) {
        const data = {
            first_name: this.first_name,
            last_name: this.last_name,
            email: this.email,
            weekly_hours: this.weekly_hours,
            business_line_id: this.business_line_id,
        };
        form.lastPut = { url, opts, data: this._transform ? this._transform(data) : data };
    },
});

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, appName: "ShiftPlanner", logoUrl: null } }),
    useForm: (initial) => {
        Object.assign(form, initial);
        return form;
    },
}));

import Show from "@/pages/Personal/Show.vue";
import EmployeeFields from "@/components/EmployeeFields.vue";
import WeeklyHoursField from "@/components/WeeklyHoursField.vue";
import HolidayList from "@/components/HolidayList.vue";
import AvailabilityGrid from "@/components/AvailabilityGrid.vue";
import ShiftNote from "@/components/ShiftNote.vue";
import TagChecklist from "@/components/TagChecklist.vue";
import QuestionChecklist from "@/components/QuestionChecklist.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

const mountShow = (holidays = [], extra = {}) =>
    mount(Show, {
        props: {
            token: "tok-1",
            employee: {
                first_name: "Jordan",
                last_name: "Lee",
                email: "jordan@example.com",
                weekly_hours: 24,
                business_line_id: null,
            },
            businessLines: [],
            holidays,
            ...extra,
        },
        global: {
            stubs: {
                CenteredLayout: {
                    template: "<div><slot name='header' /><slot /></div>",
                },
            },
        },
    });

const hidden = (w, sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

describe("Personal/Show", () => {
    it("renders the shared fields with the identity fields read-only", () => {
        const w = mountShow();
        expect(w.findComponent(EmployeeFields).props("readonlyIdentity")).toBe(true);

        const inputs = w.get('[data-testid="panel-details"]').findAll("input");
        expect(inputs).toHaveLength(3);
        expect(inputs.every((i) => i.attributes("disabled") !== undefined)).toBe(true);
    });

    it("seeds the form from the employee's current hours and business line", () => {
        mountShow([], { employee: { first_name: "J", last_name: "L", email: "j@l.c", weekly_hours: 24, business_line_id: 5 } });
        expect(form.weekly_hours).toBe(24);
        expect(form.business_line_id).toBe(5);
    });

    it("passes the business lines to EmployeeFields", () => {
        const lines = [{ id: 5, abbreviation: "PMP" }];
        const w = mountShow([], { businessLines: lines });
        expect(w.findComponent(EmployeeFields).props("businessLines")).toEqual(lines);
    });

    it("puts the weekly-hours field on the Availability tab", () => {
        const w = mountShow();
        expect(w.get('[data-testid="panel-details"]').findComponent(WeeklyHoursField).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField).exists()).toBe(true);
    });

    it("auto-saves weekly hours from the Availability tab", async () => {
        const w = mountShow();
        w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField)
            .vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(form.weekly_hours).toBe(40);
        expect(form.lastPut.url).toBe("/personal/tok-1");
        expect(form.lastPut.data).toEqual({ weekly_hours: 40, business_line_id: null });
    });

    it("saves weekly hours and business line from the Details form", async () => {
        const w = mountShow();
        await w.get('[data-testid="panel-details"] form').trigger("submit");

        expect(form.lastPut.url).toBe("/personal/tok-1");
        expect(form.lastPut.data).toEqual({ weekly_hours: 24, business_line_id: null });
    });

    it("auto-saves when the business line changes, and keeps the Save button", async () => {
        const w = mountShow([], { businessLines: [{ id: 5, abbreviation: "PMP" }] });
        form.lastPut = undefined;

        w.get('[data-testid="panel-details"]').findComponent(SelectInput)
            .vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        expect(form.business_line_id).toBe(5);
        expect(form.lastPut.data).toEqual({ weekly_hours: 24, business_line_id: 5 });
        expect(w.get('[data-testid="panel-details"] form').text()).toContain("Save");
    });

    it("auto-saves on leaving the Details tab with a dirty form", async () => {
        const w = mountShow();
        form.lastPut = undefined;
        form.isDirty = true;

        const detailsTab = w.findAll("button").find((b) => b.text() === "Details");
        await detailsTab.trigger("click");
        await w.vm.$nextTick();
        const availabilityTab = w.findAll("button").find((b) => b.text() === "Availability");
        await availabilityTab.trigger("click");
        await w.vm.$nextTick();

        expect(form.lastPut).toBeTruthy();
        expect(form.lastPut.url).toBe("/personal/tok-1");
    });

    it("does not auto-save when editable is false", async () => {
        const w = mountShow([], { editable: false, businessLines: [{ id: 5, abbreviation: "PMP" }] });
        form.lastPut = undefined;
        form.isDirty = true;

        const detailsTab = w.findAll("button").find((b) => b.text() === "Details");
        await detailsTab.trigger("click");
        await w.vm.$nextTick();
        const availabilityTab = w.findAll("button").find((b) => b.text() === "Availability");
        await availabilityTab.trigger("click");
        await w.vm.$nextTick();

        expect(form.lastPut).toBeUndefined();
    });

    it("mirrors the employee page tabs, Information first", () => {
        const w = mountShow();
        expect(w.text()).toContain("Information");
        expect(w.text()).toContain("Details");
        expect(w.text()).toContain("Availability");
        expect(w.text()).toContain("Competences");
        expect(hidden(w, '[data-testid="panel-information"]')).toBe(false);
        expect(hidden(w, '[data-testid="panel-details"]')).toBe(true);
        expect(hidden(w, '[data-testid="panel-availability"]')).toBe(true);
    });

    it("points the holiday list at the token endpoint", () => {
        const w = mountShow();
        expect(w.findComponent(HolidayList).props("endpoint")).toBe("/personal/tok-1/holidays");
    });

    it("points the availability grid at the token endpoint", () => {
        const w = mountShow();
        expect(w.findComponent(AvailabilityGrid).props("endpoint")).toBe("/personal/tok-1/availability");
    });

    it("renders the shift note on the Information tab when set", () => {
        const w = mountShow([], { shiftNoteHtml: "<p>Allowances table here</p>" });
        const note = w.findComponent(ShiftNote);
        expect(note.exists()).toBe(true);
        expect(note.props("html")).toBe("<p>Allowances table here</p>");
        expect(w.get('[data-testid="panel-information"]').text()).toContain("Allowances table here");
    });

    it("renders no shift note when the prop is absent", () => {
        expect(mountShow().findComponent(ShiftNote).exists()).toBe(false);
    });

    it("has a Competences tab with the competence checklist on the token endpoint", () => {
        const w = mountShow([], {
            competences: [{ id: 1, name: "Forklift" }],
            competenceIds: [1],
        });
        expect(w.text()).toContain("Competences");

        const byEndpoint = Object.fromEntries(
            w.findAllComponents(TagChecklist).map((l) => [l.props("endpoint"), l]),
        );
        expect(byEndpoint["/personal/tok-1/competences"].props("selectedIds")).toEqual([1]);
        expect(byEndpoint["/personal/tok-1/product-groups"]).toBeUndefined();
    });

    it("shows the questions checklist on the Availability tab, pointed at the token endpoint", () => {
        const w = mountShow([], {
            questions: [{ id: 5, text: "Can we contact you to work in the weekend?" }],
            questionAnswers: [5],
        });

        const checklist = w.findComponent(QuestionChecklist);
        expect(checklist.exists()).toBe(true);
        expect(checklist.props("endpoint")).toBe("/personal/tok-1/questions");
        expect(checklist.props("answeredIds")).toEqual([5]);
        expect(w.get('[data-testid="panel-availability"]').text()).toContain("Questions");
    });

    it("omits the questions section when no question is configured", () => {
        const w = mountShow();
        expect(w.findComponent(QuestionChecklist).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').text()).not.toContain("Questions");
    });

    it("is fully editable by default: no lock notice, controls enabled", () => {
        const w = mountShow();
        expect(w.find('[data-testid="locked-notice"]').exists()).toBe(false);
        expect(w.findComponent(AvailabilityGrid).props("disabled")).toBe(false);
        expect(w.findComponent(HolidayList).props("disabled")).toBe(false);
        expect(w.findComponent(EmployeeFields).props("disabled")).toBe(false);
        expect(w.get("form").text()).toContain("Save");
    });

    it("shows the lock notice and disables every control when editable is false", () => {
        const w = mountShow([], {
            editable: false,
            competences: [{ id: 1, name: "Forklift" }],
            competenceIds: [],
            questions: [{ id: 5, text: "Weekend?" }],
            questionAnswers: [],
        });

        expect(w.get('[data-testid="locked-notice"]').text()).toContain("closed by your planner");
        expect(w.findComponent(AvailabilityGrid).props("disabled")).toBe(true);
        expect(w.findComponent(HolidayList).props("disabled")).toBe(true);
        expect(w.findComponent(QuestionChecklist).props("disabled")).toBe(true);
        expect(w.findComponent(TagChecklist).props("disabled")).toBe(true);
        expect(w.findComponent(EmployeeFields).props("disabled")).toBe(true);
        // The Details save button is gone.
        expect(w.get("form").text()).not.toContain("Save");
    });

    it("reveals the holiday list when the Availability tab is clicked", async () => {
        const w = mountShow();
        const tab = w.findAll("button").find((b) => b.text() === "Availability");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden(w, '[data-testid="panel-availability"]')).toBe(false);
        expect(hidden(w, '[data-testid="panel-information"]')).toBe(true);
    });
});
