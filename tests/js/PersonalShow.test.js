import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.field.name": "Name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.hours_option": ":count hours",
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
    name: "",
    email: "",
    weekly_hours: null,
    errors: {},
    processing: false,
    recentlySuccessful: false,
    _transform: null,
    transform(fn) {
        this._transform = fn;
        return this;
    },
    put(url, opts) {
        const data = { name: this.name, email: this.email, weekly_hours: this.weekly_hours };
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
            employee: { name: "Jordan Lee", email: "jordan@example.com", weekly_hours: 24 },
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
    it("renders the shared fields with name and email read-only", () => {
        const w = mountShow();
        expect(w.findComponent(EmployeeFields).props("readonlyIdentity")).toBe(true);

        const inputs = w.get('[data-testid="panel-details"]').findAll("input");
        expect(inputs).toHaveLength(2);
        expect(inputs.every((i) => i.attributes("disabled") !== undefined)).toBe(true);
    });

    it("seeds the form from the employee's current hours", () => {
        mountShow();
        expect(form.weekly_hours).toBe(24);
    });

    it("saves only weekly_hours to the token URL", async () => {
        const w = mountShow();
        w.getComponent(SelectInput).vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(form.lastPut.url).toBe("/personal/tok-1");
        expect(form.lastPut.data).toEqual({ weekly_hours: 40 });
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
