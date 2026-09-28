import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = { "availability.day.reset": "Reset to default" };

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import DayResetButton from "@/components/DayResetButton.vue";

describe("DayResetButton", () => {
    it("resets the day to the default", async () => {
        const w = mount(DayResetButton, { props: { day: { date: "2026-10-05", blocked: true } } });

        expect(w.text()).toBe("Reset to default");
        await w.get("button").trigger("click");

        expect(w.emitted("apply-day")[0][0]).toEqual({ date: "2026-10-05", blocked: false, shifts: {} });
    });

    it("keeps the hidden overrides", async () => {
        const w = mount(DayResetButton, { props: { day: { date: "2026-10-05", blocked: false, hiddenOverrides: { 30: "available" } } } });

        await w.get("button").trigger("click");

        expect(w.emitted("apply-day")[0][0]).toEqual({ date: "2026-10-05", blocked: false, shifts: { 30: "available" } });
    });
});
