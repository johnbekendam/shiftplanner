import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "period.fte_hours": "Hours per FTE",
    "period.fte_hours_hint": "Weekly hours that count as one full-time equivalent.",
    "period.period_start": "Period start",
    "period.period_end": "Period end",
    "period.save": "Save period",
};

const { putSpy } = vi.hoisted(() => ({ putSpy: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) => {
        const form = reactive({
            ...initial,
            errors: {},
            processing: false,
            transform(cb) {
                this._transform = cb;
                return this;
            },
            put(...args) {
                const data = this._transform
                    ? this._transform({ ...initial, ...this })
                    : { ...this };
                putSpy(args[0], data, args[1]);
            },
        });
        return form;
    },
}));

import PeriodSettingsForm from "@/components/PeriodSettingsForm.vue";
import { NumberInput, DateInput } from "@/components/ui/Input";

beforeEach(() => putSpy.mockReset());

describe("PeriodSettingsForm", () => {
    it("seeds the fields from the period prop", () => {
        const w = mount(PeriodSettingsForm, {
            props: { period: { fte_hours: 36, period_start: "2026-01-01", period_end: "2026-03-31" } },
        });

        expect(w.findComponent(NumberInput).props("modelValue")).toBe(36);
        const dates = w.findAllComponents(DateInput);
        expect(dates[0].props("modelValue")).toBe("2026-01-01");
        expect(dates[1].props("modelValue")).toBe("2026-03-31");
    });

    it("defaults hours to 40 and dates to empty when the period is blank", () => {
        const w = mount(PeriodSettingsForm, { props: { period: {} } });
        expect(w.findComponent(NumberInput).props("modelValue")).toBe(40);
        expect(w.findAllComponents(DateInput)[0].props("modelValue")).toBe("");
    });

    it("submits to /settings/period, sending blank dates as null", async () => {
        const w = mount(PeriodSettingsForm, {
            props: { period: { fte_hours: 40, period_start: null, period_end: null } },
        });

        await w.get("form").trigger("submit");

        expect(putSpy).toHaveBeenCalledTimes(1);
        const [url, data] = putSpy.mock.calls[0];
        expect(url).toBe("/settings/period");
        expect(data).toMatchObject({ fte_hours: 40, period_start: null, period_end: null });
    });
});
