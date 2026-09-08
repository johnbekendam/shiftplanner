import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.field.name": "Name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.hours_option": ":count hours",
    "personal.title": "Your working hours",
    "personal.greeting": "Hello, :name",
    "personal.intro": "Choose how many hours you want to work each week.",
    "personal.action.save": "Save",
    "personal.saved": "Saved",
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.holidays.empty": "No holidays yet.",
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
import CompetenceChecklist from "@/components/CompetenceChecklist.vue";
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
    it("greets the employee by name", () => {
        expect(mountShow().text()).toContain("Hello, Jordan Lee");
    });

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

    it("has Details and Availability tabs, Details first", () => {
        const w = mountShow();
        expect(w.text()).toContain("Details");
        expect(w.text()).toContain("Availability");
        expect(hidden(w, '[data-testid="panel-details"]')).toBe(false);
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

    it("has a third Competences tab pointed at the token endpoint", () => {
        const w = mountShow([], { competences: [{ id: 1, name: "Forklift" }], competenceIds: [1] });
        expect(w.text()).toContain("Competences");

        const checklist = w.findComponent(CompetenceChecklist);
        expect(checklist.props("endpoint")).toBe("/personal/tok-1/competences");
        expect(checklist.props("selectedIds")).toEqual([1]);
    });

    it("reveals the holiday list when the Availability tab is clicked", async () => {
        const w = mountShow();
        const tab = w.findAll("button").find((b) => b.text() === "Availability");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden(w, '[data-testid="panel-availability"]')).toBe(false);
        expect(hidden(w, '[data-testid="panel-details"]')).toBe(true);
    });
});
