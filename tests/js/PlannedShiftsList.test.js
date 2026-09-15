import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => ({
        "planning.empty": "No planned shifts yet.",
        "planning.published": "Published",
        "planning.draft": "Draft",
    }[key] ?? key),
}));

import PlannedShiftsList from "@/components/PlannedShiftsList.vue";

const weeks = [
    {
        weekStart: "2026-09-07",
        weekEnd: "2026-09-13",
        published: true,
        assignments: [
            { date: "2026-09-08", workcenter_name: "Line 1", shift_name: "Early", start_time: "06:00", end_time: "14:00" },
        ],
    },
    {
        weekStart: "2026-09-14",
        weekEnd: "2026-09-20",
        published: false,
        assignments: [
            { date: "2026-09-15", workcenter_name: "Line 2", shift_name: "Late", start_time: "14:00", end_time: "22:00" },
        ],
    },
];

describe("PlannedShiftsList", () => {
    it("shows an empty state when there are no weeks", () => {
        const w = mount(PlannedShiftsList, { props: { weeks: [] } });
        expect(w.text()).toContain("No planned shifts yet.");
    });

    it("renders a heading per week and a row per assignment", () => {
        const w = mount(PlannedShiftsList, { props: { weeks } });

        expect(w.text()).toContain("Sep 7");
        expect(w.text()).toContain("Sep 13, 2026");
        expect(w.text()).toContain("Line 1");
        expect(w.text()).toContain("Early");
        expect(w.text()).toContain("Line 2");
        expect(w.text()).toContain("Late");
    });

    it("hides the published/draft marker by default", () => {
        const w = mount(PlannedShiftsList, { props: { weeks } });
        expect(w.text()).not.toContain("Published");
        expect(w.text()).not.toContain("Draft");
    });

    it("shows a Published or Draft marker per week when showPublishedMarker is set", () => {
        const w = mount(PlannedShiftsList, { props: { weeks, showPublishedMarker: true } });
        expect(w.text()).toContain("Published");
        expect(w.text()).toContain("Draft");
    });
});
