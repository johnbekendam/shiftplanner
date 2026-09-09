import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "dashboard.title": "Dashboard",
    "dashboard.overall": "Overall",
    "dashboard.no_period": "Set a period on the Settings page to see available FTE.",
};

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import Dashboard from "@/pages/Dashboard/Index.vue";

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
            overall: { available: [2, 1], target: 8 },
            lines: [
                { abbreviation: "PMP", description: "Pumps", available: [1, 1], target: 5 },
                { abbreviation: "VLV", description: "Valves", available: [1, 0], target: 3 },
            ],
        });

        const blocks = w.findAll('[data-testid="dashboard-block"]');
        expect(blocks).toHaveLength(3);
        expect(blocks[0].text()).toContain("Overall");
        expect(blocks[1].text()).toContain("PMP — Pumps");
        expect(blocks[2].text()).toContain("VLV — Valves");
    });
});
