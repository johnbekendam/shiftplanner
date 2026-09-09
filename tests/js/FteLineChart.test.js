import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import FteLineChart from "@/components/FteLineChart.vue";

const base = {
    title: "Overall",
    days: ["2026-01-05", "2026-01-06", "2026-01-07"],
    available: [1, 2, 1.5],
    target: 2,
};

describe("FteLineChart", () => {
    it("renders the title", () => {
        const w = mount(FteLineChart, { props: base });
        expect(w.text()).toContain("Overall");
        expect(w.find("svg title").text()).toBe("Overall");
    });

    it("plots one polyline vertex per day", () => {
        const w = mount(FteLineChart, { props: base });
        const points = w.get('[data-testid="fte-line"]').attributes("points").trim().split(/\s+/);
        expect(points).toHaveLength(3);
    });

    it("draws the target reference line inside the plot area", () => {
        const w = mount(FteLineChart, { props: base });
        const line = w.get('[data-testid="target-line"]');
        const yTop = Number(line.attributes("y1"));

        expect(line.attributes("y1")).toBe(line.attributes("y2"));
        // target 2 against a padded max of 2.2 sits near the top, below y=12.
        expect(yTop).toBeGreaterThan(12);
        expect(yTop).toBeLessThan(120);
    });

    it("uses the brand token for the series stroke", () => {
        const w = mount(FteLineChart, { props: base });
        expect(w.get('[data-testid="fte-line"]').attributes("stroke")).toBe("var(--color-brand)");
    });
});
