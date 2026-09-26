import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.day.title": ":weekday :date",
    "availability.day.block": "Block the whole day",
    "availability.day.default": "Default (:level)",
    "availability.day.no_shifts": "No shift runs on this day.",
    "availability.day.holiday_notice": "You are on holiday. Changes here have no effect until the holiday is removed.",
    "availability.day.reset": "Reset to default",
    "availability.day.apply": "Apply",
    "availability.day.cancel": "Cancel",
    "availability.weekday.1": "Mon",
    "availability.state.available": "Available",
    "availability.state.not_set": "Not set",
    "availability.state.not_preferred": "Not preferred",
    "availability.state.unavailable": "Unavailable",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key, params) => {
        let s = en[key] ?? key;
        for (const [k, v] of Object.entries(params ?? {})) s = s.replaceAll(`:${k}`, v);
        return s;
    },
}));

import DayAvailabilityDialog from "@/components/DayAvailabilityDialog.vue";
import { SelectInput, CheckboxInput } from "@/components/ui/Input";

const early = { id: 10, name: "Early", start_time: "06:00", end_time: "14:00" };
const late = { id: 20, name: "Late", start_time: "14:00", end_time: "22:00" };

const day = (overrides = {}) => ({
    date: "2026-10-05",
    holiday: false,
    blocked: false,
    changed: true,
    shifts: [
        { shift: early, defaultLevel: "available", override: null, status: "available" },
        { shift: late, defaultLevel: "not_set", override: "not_preferred", status: "not_preferred" },
    ],
    ...overrides,
});

const mountDialog = (props = {}) =>
    mount(DayAvailabilityDialog, {
        props: { open: true, day: day(), ...props },
        global: { stubs: { teleport: true, transition: false } },
    });

const button = (w, text) => w.findAll("button").find((b) => b.text() === text);

describe("DayAvailabilityDialog", () => {
    it("shows the date and one select per running shift, seeded with default or override", () => {
        const w = mountDialog();

        expect(w.text()).toContain("Mon 05-10-2026");
        const selects = w.findAllComponents(SelectInput);
        expect(selects).toHaveLength(2);
        expect(selects[0].props("modelValue")).toBe("default");
        expect(selects[0].props("options")[0]).toEqual({ value: "default", label: "Default (Available)" });
        expect(selects[1].props("modelValue")).toBe("not_preferred");
    });

    it("applies the edited day as a block flag and the shifts that differ from default", async () => {
        const w = mountDialog();

        await w.findAllComponents(SelectInput)[0].vm.$emit("update:modelValue", "unavailable");
        await w.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", "default");
        await button(w, "Apply").trigger("click");

        expect(w.emitted("apply")[0][0]).toEqual({ date: "2026-10-05", blocked: false, shifts: { 10: "unavailable" } });
    });

    it("blocks the whole day and disables the shift selects", async () => {
        const w = mountDialog();

        await w.getComponent(CheckboxInput).vm.$emit("update:modelValue", true);

        expect(w.findAllComponents(SelectInput).every((s) => s.props("disabled"))).toBe(true);
        await button(w, "Apply").trigger("click");
        expect(w.emitted("apply")[0][0]).toEqual({ date: "2026-10-05", blocked: true, shifts: { 20: "not_preferred" } });
    });

    it("resets the draft to the default without applying it yet", async () => {
        const w = mountDialog({ day: day({ blocked: true }) });

        await button(w, "Reset to default").trigger("click");

        expect(w.getComponent(CheckboxInput).props("modelValue")).toBe(false);
        expect(w.findAllComponents(SelectInput).map((s) => s.props("modelValue"))).toEqual(["default", "default"]);
        expect(w.emitted("apply")).toBeUndefined();
        await button(w, "Apply").trigger("click");
        expect(w.emitted("apply")[0][0]).toEqual({ date: "2026-10-05", blocked: false, shifts: {} });
    });

    it("shows the holiday notice and the no-shift text", () => {
        const w = mountDialog({ day: day({ holiday: true, shifts: [] }) });

        expect(w.text()).toContain("You are on holiday.");
        expect(w.text()).toContain("No shift runs on this day.");
    });

    it("is read-only when disabled: no Apply or Reset, only Cancel", async () => {
        const w = mountDialog({ disabled: true });

        expect(button(w, "Apply")).toBeUndefined();
        expect(button(w, "Reset to default")).toBeUndefined();
        expect(w.getComponent(CheckboxInput).props("disabled")).toBe(true);
        await button(w, "Cancel").trigger("click");
        expect(w.emitted("close")).toHaveLength(1);
    });
});
