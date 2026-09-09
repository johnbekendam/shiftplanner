import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "dashboard.coverage": "Hours covered",
    "dashboard.hours_ratio": ":available / :required h",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import CoverageDonut from "@/components/CoverageDonut.vue";

const arcFraction = (w) => {
    const arc = w.get('[data-testid="donut-arc"]');
    const r = Number(arc.attributes("r"));
    const circumference = 2 * Math.PI * r;
    const dash = Number(arc.attributes("stroke-dasharray").trim().split(/[\s,]+/)[0]);
    return dash / circumference;
};

describe("CoverageDonut", () => {
    it("shows the rounded coverage percentage and fills the arc to match", () => {
        const w = mount(CoverageDonut, { props: { available: 60, required: 100 } });

        expect(w.get('[data-testid="donut-label"]').text()).toBe("60%");
        expect(arcFraction(w)).toBeCloseTo(0.6, 5);
    });

    it("caps the arc at a full ring but still shows the true percentage over 100%", () => {
        const w = mount(CoverageDonut, { props: { available: 150, required: 100 } });

        expect(w.get('[data-testid="donut-label"]').text()).toBe("150%");
        expect(arcFraction(w)).toBeCloseTo(1, 5);
    });

    it("shows a dash and no arc when nothing is required", () => {
        const w = mount(CoverageDonut, { props: { available: 0, required: 0 } });

        expect(w.get('[data-testid="donut-label"]').text()).toBe("—");
        expect(w.findAll('[data-testid="donut-arc"]')).toHaveLength(0);
        expect(w.get('[data-testid="donut-caption"]').text()).toBe("0 / 0 h");
    });

    it("captions the raw hours figures", () => {
        const w = mount(CoverageDonut, { props: { available: 480, required: 800 } });

        expect(w.get('[data-testid="donut-caption"]').text()).toBe("480 / 800 h");
    });

    it("uses the brand token for the arc and the border token for the track", () => {
        const w = mount(CoverageDonut, { props: { available: 60, required: 100 } });

        expect(w.get('[data-testid="donut-arc"]').attributes("stroke")).toBe("var(--color-brand-bg)");
        expect(w.get('[data-testid="donut-track"]').attributes("stroke")).toBe("var(--color-border)");
    });
});
