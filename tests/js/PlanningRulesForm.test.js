import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "planning_rules.mode": "Mode",
    "planning_rules.mode.hard": "Hard",
    "planning_rules.mode.soft": "Soft",
    "planning_rules.severity": "Severity (1-10)",
    "planning_rules.max_hours_per_week": "Max hours per week",
    "planning_rules.max_hours_per_week_hint": "Capped at each employee's own weekly hours, averaged over 2-week calendar pairs.",
    "planning_rules.max_shifts_per_day": "Max shifts per day",
    "planning_rules.not_preferred_shift": "Not-preferred-shift assignment",
    "planning_rules.not_preferred_shift_hint": "Applies when an assignment lands on a shift the employee marked not preferred.",
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

import PlanningRulesForm from "@/components/PlanningRulesForm.vue";
import { NumberInput, SelectInput } from "@/components/ui/Input";

beforeEach(() => putSpy.mockReset());

describe("PlanningRulesForm", () => {
    it("seeds the fields from the planningRules prop", () => {
        const w = mount(PlanningRulesForm, {
            props: {
                planningRules: {
                    max_hours_per_week_mode: "soft",
                    max_hours_per_week_severity: 7,
                    max_shifts_per_day: 2,
                    max_shifts_per_day_mode: "hard",
                    not_preferred_shift_mode: "soft",
                    not_preferred_shift_severity: 4,
                },
            },
        });

        const selects = w.findAllComponents(SelectInput);
        expect(selects[0].props("modelValue")).toBe("soft");
        const numbers = w.findAllComponents(NumberInput);
        expect(numbers[0].props("modelValue")).toBe(7);
        expect(numbers.some((n) => n.props("modelValue") === 2)).toBe(true);
    });

    it("defaults to hard/hard/soft and hides severity fields when hard", () => {
        const w = mount(PlanningRulesForm, { props: { planningRules: {} } });

        const selects = w.findAllComponents(SelectInput);
        expect(selects[0].props("modelValue")).toBe("hard");
        expect(selects[1].props("modelValue")).toBe("hard");
        expect(selects[2].props("modelValue")).toBe("soft");
        // Only the not-preferred-shift severity field shows, since it defaults to soft.
        expect(w.findAllComponents(NumberInput)).toHaveLength(2);
    });

    it("reveals a severity field when a rule is switched to soft", async () => {
        const w = mount(PlanningRulesForm, { props: { planningRules: {} } });

        expect(w.findAllComponents(NumberInput)).toHaveLength(2);
        w.findAllComponents(SelectInput)[0].vm.$emit("update:modelValue", "soft");
        await w.vm.$nextTick();
        expect(w.findAllComponents(NumberInput)).toHaveLength(3);
    });

    it("submits to /settings/planning-rules, nulling severity for hard rules", async () => {
        const w = mount(PlanningRulesForm, {
            props: {
                planningRules: {
                    max_hours_per_week_mode: "soft",
                    max_hours_per_week_severity: 6,
                    max_shifts_per_day: 1,
                    max_shifts_per_day_mode: "hard",
                    max_shifts_per_day_severity: null,
                    not_preferred_shift_mode: "hard",
                    not_preferred_shift_severity: null,
                },
            },
        });

        await w.vm.submit();

        expect(putSpy).toHaveBeenCalledTimes(1);
        const [url, data] = putSpy.mock.calls[0];
        expect(url).toBe("/settings/planning-rules");
        expect(data).toMatchObject({
            max_hours_per_week_mode: "soft",
            max_hours_per_week_severity: 6,
            max_shifts_per_day_mode: "hard",
            max_shifts_per_day_severity: null,
            not_preferred_shift_mode: "hard",
            not_preferred_shift_severity: null,
        });
    });

    it("exposes isDirty reflecting the current edits, and cancel() resets them", async () => {
        const w = mount(PlanningRulesForm, { props: { planningRules: { max_shifts_per_day: 1 } } });
        expect(w.vm.isDirty).toBe(false);

        w.findAllComponents(NumberInput).find((n) => n.props("modelValue") === 1).vm.$emit("update:modelValue", 3);
        await w.vm.$nextTick();
        expect(w.vm.isDirty).toBe(true);

        w.vm.cancel();
        await w.vm.$nextTick();
        expect(w.vm.isDirty).toBe(false);
    });
});
