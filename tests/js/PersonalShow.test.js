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
};

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
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, appName: "ShiftPlanner", logoUrl: null } }),
    useForm: (initial) => {
        Object.assign(form, initial);
        return form;
    },
}));

import Show from "@/pages/Personal/Show.vue";
import EmployeeFields from "@/components/EmployeeFields.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

const mountShow = () =>
    mount(Show, {
        props: {
            token: "tok-1",
            employee: { name: "Jordan Lee", email: "jordan@example.com", weekly_hours: 24 },
        },
        global: {
            stubs: { CenteredLayout: { template: "<div><slot name='title' /><slot /></div>" } },
        },
    });

describe("Personal/Show", () => {
    it("greets the employee by name", () => {
        expect(mountShow().text()).toContain("Hello, Jordan Lee");
    });

    it("renders the shared fields with name and email read-only", () => {
        const w = mountShow();
        expect(w.findComponent(EmployeeFields).props("readonlyIdentity")).toBe(true);

        const inputs = w.findAll("input");
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
});
