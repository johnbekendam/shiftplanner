import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

vi.mock("@inertiajs/vue3", () => ({ router: { put: vi.fn(), post: vi.fn(), delete: vi.fn(), on: () => () => {} } }));
vi.mock("axios", () => ({ default: { get: vi.fn().mockResolvedValue({ data: [] }) } }));
vi.mock("@/composables/useI18n", () => ({ useI18n: () => (key) => key }));

import WorkcenterScheduleCard from "@/components/scheduling/WorkcenterScheduleCard.vue";

const cells = Array.from({ length: 7 }, (_, i) => ({
    date: `2026-09-${String(14 + i).padStart(2, "0")}`,
    spots: 1,
    overridden: false,
    assignments: [],
}));

describe("WorkcenterScheduleCard", () => {
    it("shows the workcenter name in the header and a table per shift with its name and time range", () => {
        const w = mount(WorkcenterScheduleCard, {
            props: {
                workcenter: { id: 1, name: "Line 1" },
                schedule: [
                    { shift: { id: 9, name: "Early", start_time: "06:00", end_time: "14:00" }, cells },
                    { shift: { id: 10, name: "Late", start_time: "14:00", end_time: "22:00" }, cells },
                ],
            },
        });

        expect(w.text()).toContain("Line 1");
        expect(w.text()).toContain("Early");
        expect(w.text()).toContain("06:00–14:00");
        expect(w.text()).toContain("Late");
        expect(w.text()).toContain("14:00–22:00");
        expect(w.findAll("table")).toHaveLength(2);
    });
});
