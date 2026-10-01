import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "screens.copy": "Copy link",
    "screens.copied": "Link copied",
    "screens.regenerate": "Regenerate link",
    "screens.regenerate_confirm": "Regenerate",
    "screens.schedule.title": "Schedule link",
    "screens.schedule.help": "Anyone with this link sees the schedule without an account.",
    "screens.schedule.regenerate_title": "Regenerate the schedule link?",
    "screens.schedule.regenerate_body": "The old schedule link stops working at once.",
    "screens.live.title": "Live screens",
    "screens.live.help": "One wall screen for each workcenter.",
    "screens.live.empty": "No active workcenters.",
    "screens.live.regenerate_title": "Regenerate the live-screen link?",
    "screens.live.regenerate_body": "The old link stops working at once.",
};

const { router } = vi.hoisted(() => ({ router: { post: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import ScreensPanel from "@/components/ScreensPanel.vue";

const scheduleUrl = "https://app.test/schedule/secret";
const workcenters = [
    { id: 1, name: "Assembly", archived_at: null, live_url: "https://app.test/live/aaa" },
    { id: 2, name: "Old line", archived_at: "2026-09-01T00:00:00Z", live_url: "https://app.test/live/old" },
    { id: 3, name: "Packing", archived_at: null, live_url: "https://app.test/live/ccc" },
];

const mountPanel = (props = {}) => mount(ScreensPanel, { props: { scheduleUrl, workcenters, ...props } });
const dialog = (w, title) => w.findAllComponents({ name: "ConfirmDialog" }).find((d) => d.props("title") === title);

beforeEach(() => {
    router.post.mockReset();
});

describe("ScreensPanel", () => {
    it("shows the schedule link row first, without the URL", () => {
        const w = mountPanel();
        const rows = w.findAll("[data-testid^='screen-row-']");

        expect(rows[0].attributes("data-testid")).toBe("screen-row-schedule");
        expect(rows[0].text()).toContain("Schedule link");
        expect(w.text()).not.toContain(scheduleUrl);
    });

    it("lists one live-screen row per active workcenter, in order", () => {
        const w = mountPanel();

        expect(w.text()).toContain("Live screens");
        expect(w.findAll("[data-testid^='screen-row-workcenter-']").map((r) => r.text())).toEqual(["Assembly", "Packing"]);
    });

    it("shows an empty message when no workcenter is active", () => {
        const w = mountPanel({ workcenters: [workcenters[1]] });

        expect(w.text()).toContain("No active workcenters.");
        expect(w.findAll("[data-testid^='screen-row-workcenter-']")).toHaveLength(0);
    });

    it("copies a workcenter's live URL", async () => {
        const writeText = vi.fn().mockResolvedValue();
        Object.assign(navigator, { clipboard: { writeText } });
        const w = mountPanel();

        await w.get("[data-testid='screen-row-workcenter-3'] [data-testid='screen-copy']").trigger("click");
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith("https://app.test/live/ccc");
        expect(w.get("[data-testid='screen-row-workcenter-3'] [data-testid='screen-copy']").attributes("aria-label")).toBe("Link copied");
    });

    it("does not fail when the clipboard is not available", async () => {
        Object.assign(navigator, { clipboard: undefined });
        const w = mountPanel();

        await w.get("[data-testid='screen-row-workcenter-1'] [data-testid='screen-copy']").trigger("click");
        await flushPromises();

        expect(w.get("[data-testid='screen-row-workcenter-1'] [data-testid='screen-copy']").attributes("aria-label")).toBe("Copy link");
    });

    it("copies the schedule URL", async () => {
        const writeText = vi.fn().mockResolvedValue();
        Object.assign(navigator, { clipboard: { writeText } });
        const w = mountPanel();

        await w.get("[data-testid='screen-row-schedule'] [data-testid='screen-copy']").trigger("click");
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith(scheduleUrl);
    });

    it("regenerates the schedule link only after the confirm", async () => {
        const w = mountPanel();

        await w.get("[data-testid='screen-row-schedule'] [data-testid='screen-regenerate']").trigger("click");
        expect(router.post).not.toHaveBeenCalled();

        await dialog(w, "Regenerate the schedule link?").vm.$emit("confirm");

        expect(router.post).toHaveBeenCalledWith("/settings/schedule-token", {}, { preserveScroll: true, preserveState: true });
    });

    it("regenerates a workcenter's live link only after the confirm", async () => {
        const w = mountPanel();

        await w.get("[data-testid='screen-row-workcenter-1'] [data-testid='screen-regenerate']").trigger("click");
        const confirm = w.findAllComponents({ name: "ConfirmDialog" }).find((d) => d.props("open"));
        expect(confirm.props("title")).toBe("Regenerate the live-screen link?");
        expect(router.post).not.toHaveBeenCalled();

        await confirm.vm.$emit("confirm");

        expect(router.post).toHaveBeenCalledWith("/settings/workcenters/1/live-token", {}, { preserveScroll: true, preserveState: true });
    });

    it("does nothing when a regenerate is cancelled", async () => {
        const w = mountPanel();

        await w.get("[data-testid='screen-row-workcenter-1'] [data-testid='screen-regenerate']").trigger("click");
        await w.findAllComponents({ name: "ConfirmDialog" }).find((d) => d.props("open")).vm.$emit("cancel");

        expect(router.post).not.toHaveBeenCalled();
        expect(w.findAllComponents({ name: "ConfirmDialog" }).some((d) => d.props("open"))).toBe(false);
    });
});
