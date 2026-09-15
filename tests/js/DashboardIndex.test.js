import { beforeEach, describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "dashboard.title": "Dashboard",
    "dashboard.overall": "Overall",
    "dashboard.no_period": "Set a period on the Settings page to see available FTE.",
    "dashboard.unconfirmed_employees": ":count unconfirmed employees are not included in these numbers.",
    "dashboard.employee_filter.confirmed": "Confirmed",
    "dashboard.employee_filter.unconfirmed": "Unconfirmed",
    "dashboard.employee_filter.both": "Stacked",
    "dashboard.coverage": "Hours covered",
    "dashboard.hours_ratio": ":available / :required h",
};

const routerGet = vi.hoisted(() => vi.fn());

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { translations: en } }),
    router: { get: routerGet },
}));

import Dashboard from "@/pages/Dashboard/Index.vue";
import FteLineChart from "@/components/FteLineChart.vue";
import CoverageDonut from "@/components/CoverageDonut.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (props = {}) => mount(Dashboard, { props, global: { stubs } });

describe("Dashboard/Index", () => {
    beforeEach(() => {
        routerGet.mockClear();
    });

    it("prompts for a period when none is set", () => {
        const w = mountPage({ period: null });
        expect(w.text()).toContain("Set a period on the Settings page");
        expect(w.findAll('[data-testid="dashboard-block"]')).toHaveLength(0);
    });

    it("shows all employee status filter options", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            overall: { available: [1, 1], target: 8, available_hours: 16, required_hours: 64 },
            lines: [],
        });

        const options = w.findAll('[data-testid="dashboard-employee-filter"]');
        expect(options).toHaveLength(3);
        expect(options[2].element.tagName).toBe("BUTTON");
        expect(options.map((option) => option.text())).toEqual(["Unconfirmed", "Confirmed", "Stacked"]);
        expect(options[2].attributes("aria-pressed")).toBe("true");
        expect(options[2].classes()).toContain("outline");
        expect(options[2].classes()).toContain("outline-2");
        expect(options[2].classes()).toContain("outline-offset-2");
        expect(options[2].classes()).toContain("outline-[var(--color-brand-bg)]");
        expect(options[0].classes()).not.toContain("outline-2");

        const legendLines = w.findAll('[data-testid="dashboard-employee-filter-line"]');
        expect(legendLines).toHaveLength(3);
        expect(legendLines[0].classes()).toContain("bg-[var(--color-text-secondary)]");
        expect(legendLines[1].classes()).toContain("bg-[var(--color-badge-success-text)]");
        expect(legendLines[2].classes()).toContain("bg-[var(--color-brand-bg)]");
    });

    it("navigates with a query string when confirmed is selected", async () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            employeeStatusFilter: "both",
            overall: { available: [1, 1], target: 8, available_hours: 16, required_hours: 64 },
            lines: [],
        });

        await w.findAll('[data-testid="dashboard-employee-filter"]')[1].trigger("click");

        expect(routerGet).toHaveBeenCalledWith("/dashboard", { employees: "confirmed" }, { preserveScroll: true, preserveState: true });
    });

    it("uses no employee query string when stacked is selected", async () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            employeeStatusFilter: "confirmed",
            overall: { available: [1, 1], target: 8, available_hours: 16, required_hours: 64 },
            lines: [],
        });

        await w.findAll('[data-testid="dashboard-employee-filter"]')[2].trigger("click");

        expect(routerGet).toHaveBeenCalledWith("/dashboard", {}, { preserveScroll: true, preserveState: true });
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

    it("lets the dashboard cards fill the page body width", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            overall: { available: [2, 1], target: 8, available_hours: 24, required_hours: 64 },
            lines: [],
        });

        const grid = w.get('[data-testid="dashboard-card-grid"]');
        expect(grid.classes()).toContain("w-full");
        expect(grid.classes()).not.toContain("md:w-1/2");
    });

    it("shows one dashboard notice when unconfirmed employees are excluded", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            employeeStatusFilter: "confirmed",
            overall: { available: [1, 1], target: 8, available_hours: 16, required_hours: 64 },
            lines: [],
            unconfirmedEmployeeCount: 3,
        });

        expect(w.text()).toContain("3 unconfirmed employees are not included in these numbers.");
        expect(w.findAll('[data-testid="unconfirmed-employees-notice"]')).toHaveLength(1);
    });

    it("hides the unconfirmed notice when everyone is confirmed", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            overall: { available: [1, 1], target: 8, available_hours: 16, required_hours: 64 },
            lines: [],
            unconfirmedEmployeeCount: 0,
        });

        expect(w.find('[data-testid="unconfirmed-employees-notice"]').exists()).toBe(false);
    });

    it("renders confirmed, unconfirmed, and the blue sum line in stacked (both) mode", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            employeeStatusFilter: "both",
            overall: {
                available: [1.5, 1.25],
                available_confirmed: [1, 1],
                available_unconfirmed: [0.5, 0.25],
                target: 8,
                available_hours: 22,
                required_hours: 64,
            },
            lines: [],
        });

        const chart = w.getComponent(FteLineChart);
        expect(chart.props("available")).toEqual([1.5, 1.25]);
        expect(chart.props("availableStroke")).toBe("var(--color-brand-bg)");
        expect(chart.props("baseAvailable")).toEqual([1, 1]);
        expect(chart.props("baseAvailableStroke")).toBe("var(--color-badge-success-text)");
        expect(chart.props("secondaryAvailable")).toEqual([0.5, 0.25]);
        expect(chart.props("secondaryAvailableStroke")).toBe("var(--color-text-secondary)");
    });

    it("shows only the main line in confirmed-only and unconfirmed-only modes", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            employeeStatusFilter: "confirmed",
            overall: { available: [1, 1], target: 8, available_hours: 16, required_hours: 64 },
            lines: [],
        });

        const chart = w.getComponent(FteLineChart);
        expect(chart.props("baseAvailable")).toBe(null);
        expect(chart.props("secondaryAvailable")).toBe(null);
    });

    it("uses gray for the unconfirmed-only line", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            employeeStatusFilter: "unconfirmed",
            overall: { available: [0.5, 0.25], target: 8, available_hours: 6, required_hours: 64 },
            lines: [],
        });

        const chart = w.getComponent(FteLineChart);
        expect(chart.props("availableStroke")).toBe("var(--color-text-secondary)");
    });

    it("uses green for the confirmed-only line", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            employeeStatusFilter: "confirmed",
            overall: { available: [1, 1], target: 8, available_hours: 16, required_hours: 64 },
            lines: [],
        });

        const chart = w.getComponent(FteLineChart);
        expect(chart.props("availableStroke")).toBe("var(--color-badge-success-text)");
    });

    it("links each card header to the matching filtered employees view", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            overall: { available: [2, 1], target: 8, available_hours: 24, required_hours: 64 },
            lines: [
                { id: 7, abbreviation: "PMP", description: "Pumps", available: [1, 1], target: 5, available_hours: 16, required_hours: 40 },
            ],
        });

        const headerLinks = w.findAll('[data-testid="dashboard-block-header-link"]');
        expect(headerLinks).toHaveLength(2);
        expect(headerLinks[0].attributes("href")).toBe("/employees");
        expect(headerLinks[1].attributes("href")).toBe("/employees?business_lines[]=7");
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
