import { beforeEach, describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "dashboard.title": "Dashboard",
    "dashboard.overall": "Overall",
    "dashboard.no_period": "Set a period on the Settings page to see available FTE.",
    "dashboard.lines.label": "Chart lines",
    "dashboard.lines.demand": "Demand",
    "dashboard.lines.available": "Available",
    "dashboard.lines.planned": "Planned",
    "dashboard.coverage": "Hours covered",
    "dashboard.hours_ratio": ":available / :required h",
};

const routerGet = vi.hoisted(() => vi.fn());
const page = vi.hoisted(() => ({ url: "/dashboard" }));

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { translations: en }, url: page.url }),
    router: { get: routerGet },
}));

import Dashboard from "@/pages/Dashboard/Index.vue";
import FteLineChart from "@/components/FteLineChart.vue";
import CoverageDonut from "@/components/CoverageDonut.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (props = {}, url = "/dashboard") => {
    page.url = url;
    return mount(Dashboard, { props, global: { stubs } });
};

const period = { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 };
const days = ["2026-01-05", "2026-01-06"];
const overall = {
    demand: [1.5, 1.5],
    available: [1, 1],
    planned: [0.75, 0.75],
    target: 8,
    available_hours: 16,
    required_hours: 64,
};
const toggles = (w) => w.findAll('[data-testid="dashboard-line-toggle"]');
const checked = (w) => toggles(w).map((t) => t.element.checked);
const chartLines = (w) => w.getComponent(FteLineChart).props("lines");

describe("Dashboard/Index", () => {
    beforeEach(() => {
        routerGet.mockClear();
    });

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
        }, "/dashboard?lines=available");

        const blocks = w.findAll('[data-testid="dashboard-block"]');
        expect(blocks).toHaveLength(3);
        expect(blocks[0].text()).toContain("Overall");
        expect(blocks[1].text()).toContain("PMP — Pumps");
        expect(blocks[2].text()).toContain("VLV — Valves");

        const charts = w.findAllComponents(FteLineChart);
        expect(charts).toHaveLength(3);
        expect(charts[0].props("title")).toBe("Overall");
        expect(charts[0].props("lines").find((line) => line.key === "available").values).toEqual([2, 1]);
        expect(charts[1].props("lines").find((line) => line.key === "available").values).toEqual([1, 1]);
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

    it("gives each block a coverage donut fed available hours", () => {
        const w = mountPage({
            period: { start: "2026-01-05", end: "2026-01-06", fte_hours: 40 },
            days: ["2026-01-05", "2026-01-06"],
            overall: { available: [2, 1], target: 8, available_hours: 18, required_hours: 64 },
            lines: [
                { abbreviation: "PMP", description: "Pumps", available: [1, 1], target: 5, available_hours: 12, required_hours: 40 },
            ],
        });

        const donuts = w.findAllComponents(CoverageDonut);
        expect(donuts).toHaveLength(2);
        expect(donuts[0].props("available")).toBe(18);
        expect(donuts[0].props("required")).toBe(64);
        expect(donuts[1].props("available")).toBe(12);
        expect(donuts[1].props("required")).toBe(40);
    });

    it("shows the line toggles with color markers in order", () => {
        const w = mountPage({ period, days, overall, lines: [] });

        const boxes = toggles(w);
        expect(boxes.map((b) => b.attributes("type"))).toEqual(["checkbox", "checkbox", "checkbox"]);
        expect(boxes.map((b) => b.element.closest("label").textContent.trim())).toEqual(["Demand", "Available", "Planned"]);
        expect(w.findAll("button")).toHaveLength(0);

        const markers = w.findAll('[data-testid="dashboard-line-toggle-marker"]');
        expect(markers.map((m) => m.classes().find((c) => c.startsWith("bg-")))).toEqual([
            "bg-[var(--color-brand-bg)]",
            "bg-[var(--color-badge-success-text)]",
            "bg-[var(--color-badge-warning-text)]",
        ]);
    });

    it("turns on every line by default", () => {
        const w = mountPage({ period, days, overall, lines: [] });

        expect(checked(w)).toEqual([true, true, true]);
        expect(chartLines(w)).toEqual([
            { key: "demand", values: [1.5, 1.5], stroke: "var(--color-brand-bg)", step: true },
            { key: "available", values: [1, 1], stroke: "var(--color-badge-success-text)", step: false },
            { key: "planned", values: [0.75, 0.75], stroke: "var(--color-badge-warning-text)", step: true },
        ]);
    });

    it("draws the demand line on the overall card only", () => {
        const w = mountPage({
            period,
            days,
            overall,
            lines: [{ id: 7, abbreviation: "PMP", description: "Pumps", available: [1, 1], planned: [0.5, 0.5], target: 5, available_hours: 16, required_hours: 40 }],
        });

        const charts = w.findAllComponents(FteLineChart);
        expect(charts[0].props("lines").map((line) => line.key)).toEqual(["demand", "available", "planned"]);
        expect(charts[1].props("lines").map((line) => line.key)).toEqual(["available", "planned"]);
    });

    it("reads the visible lines from the query string", () => {
        const w = mountPage({ period, days, overall, lines: [] }, "/dashboard?lines=planned,demand,bogus");

        expect(checked(w)).toEqual([true, false, true]);
        expect(chartLines(w).map((line) => line.key)).toEqual(["demand", "planned"]);
    });

    it("ignores the old line names in the query string", () => {
        const w = mountPage({ period, days, overall, lines: [] }, "/dashboard?lines=confirmed,unconfirmed,total");

        expect(checked(w)).toEqual([false, false, false]);
        expect(chartLines(w)).toEqual([]);
    });

    it("draws no lines when the query string turns them all off", () => {
        const w = mountPage({ period, days, overall, lines: [] }, "/dashboard?lines=");

        expect(checked(w)).toEqual([false, false, false]);
        expect(chartLines(w)).toEqual([]);
    });

    it("switches a line off by removing it from the query string", async () => {
        const w = mountPage({ period, days, overall, lines: [] });

        await toggles(w)[0].setValue(false);

        expect(routerGet).toHaveBeenCalledWith(
            "/dashboard",
            { lines: "available,planned" },
            { preserveScroll: true, preserveState: true },
        );
    });

    it("sends an empty set when the last line goes off", async () => {
        const w = mountPage({ period, days, overall, lines: [] }, "/dashboard?lines=planned");

        await toggles(w)[2].setValue(false);

        expect(routerGet).toHaveBeenCalledWith("/dashboard", { lines: "" }, { preserveScroll: true, preserveState: true });
    });

    it("drops the query string when the toggles match the default", async () => {
        const w = mountPage({ period, days, overall, lines: [] }, "/dashboard?lines=demand,available");

        await toggles(w)[2].setValue(true);

        expect(routerGet).toHaveBeenCalledWith("/dashboard", {}, { preserveScroll: true, preserveState: true });
    });
});
