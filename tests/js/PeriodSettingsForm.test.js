import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "period.fte_hours": "Hours per FTE",
    "period.fte_hours_hint": "Weekly hours that count as one full-time equivalent.",
    "period.weekly_hours_minimum": "Minimum weekly hours",
    "period.weekly_hours_minimum_hint": "Weekly hours below this value show a warning.",
    "period.period_start": "Period start",
    "period.period_end": "Period end",
    "general.allow_employee_changes": "Allow employees to change their own details",
    "general.allow_employee_changes_hint": "When off, personal pages stay visible but read-only.",
};

const { putSpy } = vi.hoisted(() => ({ putSpy: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) => {
        const form = reactive({
            ...initial,
            errors: {},
            processing: false,
            _defaults: { ...initial },
            get isDirty() {
                return Object.keys(initial).some((k) => this[k] !== this._defaults[k]);
            },
            defaults() {
                this._defaults = Object.fromEntries(Object.keys(initial).map((k) => [k, this[k]]));
            },
            reset() {
                Object.assign(this, this._defaults);
            },
            clearErrors() {
                this.errors = {};
            },
            transform(cb) {
                this._transform = cb;
                return this;
            },
            put(url, opts) {
                const data = this._transform ? this._transform({ ...initial, ...this }) : { ...this };
                putSpy(url, data, opts);
                opts?.onSuccess?.();
            },
        });
        return form;
    },
}));

import PeriodSettingsForm from "@/components/PeriodSettingsForm.vue";
import { NumberInput, DateInput, CheckboxInput } from "@/components/ui/Input";

beforeEach(() => putSpy.mockReset());

describe("PeriodSettingsForm", () => {
    it("seeds the fields from the period prop", () => {
        const w = mount(PeriodSettingsForm, {
            props: { period: { fte_hours: 36, weekly_hours_minimum: 24, period_start: "2026-01-01", period_end: "2026-03-31" } },
        });

        const numbers = w.findAllComponents(NumberInput);
        expect(numbers[0].props("modelValue")).toBe(36);
        expect(numbers[1].props()).toMatchObject({ modelValue: 24, min: 1, max: 48 });
        const dates = w.findAllComponents(DateInput);
        expect(dates[0].props("modelValue")).toBe("2026-01-01");
        expect(dates[1].props("modelValue")).toBe("2026-03-31");
    });

    it("defaults hours to 40 and dates to empty when the period is blank", () => {
        const w = mount(PeriodSettingsForm, { props: { period: {} } });
        expect(w.findAllComponents(NumberInput)[0].props("modelValue")).toBe(40);
        expect(w.findAllComponents(NumberInput)[1].props("modelValue")).toBe(20);
        expect(w.findAllComponents(DateInput)[0].props("modelValue")).toBe("");
    });

    it("submits to /settings/period via the exposed submit(), sending blank dates as null", async () => {
        const w = mount(PeriodSettingsForm, {
            props: { period: { fte_hours: 40, weekly_hours_minimum: 20, period_start: null, period_end: null } },
        });

        await w.vm.submit();

        expect(putSpy).toHaveBeenCalledTimes(1);
        const [url, data] = putSpy.mock.calls[0];
        expect(url).toBe("/settings/period");
        expect(data).toMatchObject({ fte_hours: 40, weekly_hours_minimum: 20, period_start: null, period_end: null });
    });

    it("seeds the employee-changes toggle from the period prop and defaults it on", () => {
        const on = mount(PeriodSettingsForm, { props: { period: {} } });
        expect(on.findComponent(CheckboxInput).props("modelValue")).toBe(true);

        const off = mount(PeriodSettingsForm, {
            props: { period: { allow_employee_changes: false } },
        });
        expect(off.findComponent(CheckboxInput).props("modelValue")).toBe(false);
    });

    it("submits the employee-changes flag", async () => {
        const w = mount(PeriodSettingsForm, {
            props: { period: { fte_hours: 40, allow_employee_changes: false } },
        });

        await w.vm.submit();

        expect(putSpy.mock.calls[0][1]).toMatchObject({ allow_employee_changes: false });
    });

    it("exposes isDirty reflecting the current edits, and cancel() resets them", async () => {
        const w = mount(PeriodSettingsForm, { props: { period: { fte_hours: 40 } } });
        expect(w.vm.isDirty).toBe(false);

        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 32);
        await w.vm.$nextTick();
        expect(w.vm.isDirty).toBe(true);

        w.vm.cancel();
        await w.vm.$nextTick();
        expect(w.vm.isDirty).toBe(false);
        expect(w.findAllComponents(NumberInput)[0].props("modelValue")).toBe(40);
    });
});
