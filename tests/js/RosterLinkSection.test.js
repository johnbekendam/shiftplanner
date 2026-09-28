import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "settings.roster_link.title": "Roster link",
    "settings.roster_link.help": "Anyone with this link sees the roster without an account.",
    "settings.roster_link.copy": "Copy link",
    "settings.roster_link.copied": "Link copied",
    "settings.roster_link.regenerate": "Regenerate link",
    "settings.roster_link.regenerate_title": "Regenerate the roster link?",
    "settings.roster_link.regenerate_body": "The old link stops working at once.",
    "settings.roster_link.regenerate_confirm": "Regenerate",
};

const { router } = vi.hoisted(() => ({ router: { post: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import RosterLinkSection from "@/components/RosterLinkSection.vue";

const url = "https://app.test/roster/secret-token";

beforeEach(() => {
    router.post.mockReset();
});

describe("RosterLinkSection", () => {
    it("shows the roster URL", () => {
        const w = mount(RosterLinkSection, { props: { url } });

        expect(w.text()).toContain("Roster link");
        expect(w.text()).toContain(url);
    });

    it("copies the URL to the clipboard", async () => {
        const writeText = vi.fn().mockResolvedValue();
        Object.assign(navigator, { clipboard: { writeText } });
        const w = mount(RosterLinkSection, { props: { url } });

        await w.get("[data-testid='roster-link-copy']").trigger("click");
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith(url);
        expect(w.get("[data-testid='roster-link-copy']").attributes("aria-label")).toBe("Link copied");
    });

    it("regenerates the link only after the confirm", async () => {
        const w = mount(RosterLinkSection, { props: { url } });

        await w.get("[data-testid='roster-link-regenerate']").trigger("click");
        expect(w.findComponent({ name: "ConfirmDialog" }).props("open")).toBe(true);
        expect(router.post).not.toHaveBeenCalled();

        await w.findComponent({ name: "ConfirmDialog" }).vm.$emit("confirm");

        expect(router.post).toHaveBeenCalledWith("/settings/roster-token", {}, expect.objectContaining({ preserveScroll: true }));
        expect(w.findComponent({ name: "ConfirmDialog" }).props("open")).toBe(false);
    });
});
