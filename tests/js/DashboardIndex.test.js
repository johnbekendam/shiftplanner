import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "dashboard.title": "Dashboard",
    "dashboard.overall": "Overall",
    "dashboard.no_period": "Set a period on the Settings page to see available FTE.",
    "dashboard.coverage": "Hours covered",
    "dashboard.hours_ratio": ":available / :required h",
};

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import Dashboard from "@/pages/Dashboard/Index.vue";
import FteLineChart from "@/components/FteLineChart.vue";
import CoverageDonut from "@/components/CoverageDonut.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (props = {}) => mount(Dashboard, { props, global: { stubs } });

describe("Dashboard/Index", () => {
    it("prompts for a period when none is set", () => {
        const w = mountPage({ period: null });
        expect(w.text()).toContain("Set a period on the Settings page");
        expect(w.findAll('[data-testid="dashboard-block"]')).toHaveLength(0);
    });

    it("shows an overall block first, then one per business line", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            overall: { available: [2, 1], target: 8, available_hours: 24, required_hours: 64 },
            lines: [
                { abbreviation: "PMP", description: "Pumps", available: [1, 1], target: 5, available_hours: 16, required_hours: 40 },
                { abbreviation: "VLV", description: "Valves", available: [1, 0], target: 3, available_hours: 8, required_hours: 24 },
            ],
        });

        const blocks = w.findAll('[data-testid="dashboard-block"]');
        expect(blocks).toHaveLength(3);
        expect(blocks[0].text()).toContain("Overall");
        expect(blocks[1].text()).toContain("PMP — Pumps");
        expect(blocks[2].text()).toContain("VLV — Valves");

        const charts = w.findAllComponents(FteLineChart);
        expect(charts).toHaveLength(3);
        expect(charts[0].props("title")).toBe("Overall");
        expect(charts[0].props("available")).toEqual([2, 1]);
        expect(charts[1].props("target")).toBe(5);
    });

    it("gives each block a coverage donut fed the block's hours", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            overall: { available: [2, 1], target: 8, available_hours: 24, required_hours: 64 },
            lines: [
                { abbreviation: "PMP", description: "Pumps", available: [1, 1], target: 5, available_hours: 16, required_hours: 40 },
            ],
        });

        const donuts = w.findAllComponents(CoverageDonut);
        expect(donuts).toHaveLength(2);
        expect(donuts[0].props("available")).toBe(24);
        expect(donuts[0].props("required")).toBe(64);
        expect(donuts[1].props("available")).toBe(16);
        expect(donuts[1].props("required")).toBe(40);
    });
});
