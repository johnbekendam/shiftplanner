import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import FteLineChart from "@/components/FteLineChart.vue";

const base = {
    title: "Overall",
    days: ["2026-01-05", "2026-01-06", "2026-01-07"],
    lines: [{ key: "total", values: [1, 2, 1.5], stroke: "var(--color-brand-bg)" }],
    target: 2,
};

const pointsOf = (w, key) => w.get(`[data-testid="fte-line-${key}"]`).attributes("points").trim().split(/\s+/);

describe("FteLineChart", () => {
    it("renders the title", () => {
        const w = mount(FteLineChart, { props: base });
        expect(w.text()).toContain("Overall");
        expect(w.find("svg title").text()).toBe("Overall");
    });

    it("plots one polyline vertex per day", () => {
        const w = mount(FteLineChart, { props: base });
        expect(pointsOf(w, "total")).toHaveLength(3);
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

    it("draws one polyline per line, each in its own stroke", () => {
        const w = mount(FteLineChart, {
            props: {
                ...base,
                lines: [
                    { key: "unconfirmed", values: [0.5, 0.5, 0.5], stroke: "var(--color-text-secondary)" },
                    { key: "confirmed", values: [1, 2, 1.5], stroke: "var(--color-badge-success-text)" },
                    { key: "planned", values: [1, 1, 1], stroke: "var(--color-badge-warning-text)" },
                ],
            },
        });

        expect(w.findAll("polyline")).toHaveLength(3);
        expect(w.get('[data-testid="fte-line-unconfirmed"]').attributes("stroke")).toBe("var(--color-text-secondary)");
        expect(w.get('[data-testid="fte-line-confirmed"]').attributes("stroke")).toBe("var(--color-badge-success-text)");
        expect(w.get('[data-testid="fte-line-planned"]').attributes("stroke")).toBe("var(--color-badge-warning-text)");
    });

    it("draws only the target line when no lines are given", () => {
        const w = mount(FteLineChart, { props: { ...base, lines: [] } });
        expect(w.findAll("polyline")).toHaveLength(0);
        expect(w.find('[data-testid="target-line"]').exists()).toBe(true);
    });

    it("scales the y-axis to the given lines and the target", () => {
        const w = mount(FteLineChart, {
            props: { ...base, target: 1, lines: [{ key: "planned", values: [10, 10, 10], stroke: "x" }] },
        });
        const labels = w.findAll("text").map((t) => t.text());
        expect(labels).toContain("12");
    });

    it("draws a step line with a vertical jump between days that change value", () => {
        const w = mount(FteLineChart, {
            props: {
                ...base,
                lines: [{ key: "planned", values: [1, 1, 2], stroke: "x", step: true }],
            },
        });

        const points = pointsOf(w, "planned").map((p) => p.split(",").map(Number));
        // Three day vertices plus two at the midpoint before the change.
        expect(points).toHaveLength(5);
        const [, second, midLow, midHigh, third] = points;
        expect(midLow[0]).toBe(midHigh[0]);
        expect(midLow[0]).toBeGreaterThan(second[0]);
        expect(midLow[0]).toBeLessThan(third[0]);
        expect(midLow[1]).toBe(second[1]);
        expect(midHigh[1]).toBe(third[1]);
    });
});
