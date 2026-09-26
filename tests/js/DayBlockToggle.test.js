import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = { "availability.day.block": "Block the whole day" };

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import DayBlockToggle from "@/components/DayBlockToggle.vue";
import { CheckboxInput } from "@/components/ui/Input";

const day = (overrides = {}) => ({
    date: "2026-10-05",
    blocked: false,
    shifts: [
        { shift: { id: 10 }, override: null },
        { shift: { id: 20 }, override: "not_preferred" },
    ],
    ...overrides,
});

describe("DayBlockToggle", () => {
    it("shows the block state and emits the whole day, keeping the shift overrides", async () => {
        const w = mount(DayBlockToggle, { props: { day: day() } });

        expect(w.text()).toBe("Block the whole day");
        expect(w.getComponent(CheckboxInput).props("modelValue")).toBe(false);

        await w.getComponent(CheckboxInput).vm.$emit("update:modelValue", true);

        expect(w.emitted("apply-day")[0][0]).toEqual({ date: "2026-10-05", blocked: true, shifts: { 20: "not_preferred" } });
    });

    it("is read-only when disabled", () => {
        const w = mount(DayBlockToggle, { props: { day: day({ blocked: true }), disabled: true } });

        expect(w.getComponent(CheckboxInput).props()).toMatchObject({ modelValue: true, disabled: true });
    });
});
