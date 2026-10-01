import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = { "nav.whats_new": "What's new", "whats_new.title": "What's new", "whats_new.close": "Close" };

const state = vi.hoisted(() => ({ whatsNew: null }));
const axiosPost = vi.hoisted(() => vi.fn(() => Promise.resolve({})));

vi.mock("axios", () => ({ default: { post: axiosPost } }));
vi.mock("@inertiajs/vue3", () => ({
    router: { post: vi.fn() },
    usePage: () => ({
        props: { translations: en, auth: { user: { id: 1, role: "manager" } }, whatsNew: state.whatsNew },
        url: "/dashboard",
    }),
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

import AppLayout from "@/layouts/AppLayout.vue";
import WhatsNewDialog from "@/components/WhatsNewDialog.vue";

const stubs = {
    Layout: { template: "<div><slot name='sidebar' /><slot /></div>" },
    AppLogo: true,
    FlashMessage: true,
};

const ENTRIES = [
    { id: "b", date: "2026-10-01", title: "New", html: "<p>New.</p>" },
    { id: "a", date: "2026-09-01", title: "Old", html: "<p>Old.</p>" },
];

const mountLayout = () => mount(AppLayout, { global: { stubs } });
const dialog = (w) => w.getComponent(WhatsNewDialog);
const link = (w) => w.find('[data-testid="whats-new-link"]');

beforeEach(() => {
    state.whatsNew = null;
    axiosPost.mockClear();
});

describe("AppLayout What's new", () => {
    it("opens the dialog with only the unseen entries", () => {
        state.whatsNew = { entries: ENTRIES, seenAt: "2026-09-01" };
        const w = mountLayout();

        expect(dialog(w).props("open")).toBe(true);
        expect(dialog(w).props("entries").map((e) => e.id)).toEqual(["b"]);
    });

    it("opens the dialog with every entry when nothing was seen yet", () => {
        state.whatsNew = { entries: ENTRIES, seenAt: null };
        expect(dialog(mountLayout()).props("entries")).toHaveLength(2);
    });

    it("does not open the dialog when every entry was seen", () => {
        state.whatsNew = { entries: ENTRIES, seenAt: "2026-10-01" };
        expect(dialog(mountLayout()).props("open")).toBe(false);
    });

    it("marks the entries as seen when the dialog closes", async () => {
        state.whatsNew = { entries: ENTRIES, seenAt: "2026-09-01" };
        const w = mountLayout();

        dialog(w).vm.$emit("close");
        await w.vm.$nextTick();

        expect(dialog(w).props("open")).toBe(false);
        expect(axiosPost).toHaveBeenCalledWith("/whats-new/seen");
    });

    it("reopens every entry from the sidebar link, without marking anything again", async () => {
        state.whatsNew = { entries: ENTRIES, seenAt: "2026-10-01" };
        const w = mountLayout();

        await link(w).trigger("click");

        expect(dialog(w).props("open")).toBe(true);
        expect(dialog(w).props("entries")).toHaveLength(2);
        expect(link(w).text()).toBe("What's new");

        dialog(w).vm.$emit("close");
        await w.vm.$nextTick();
        expect(axiosPost).not.toHaveBeenCalled();
    });

    it("hides the sidebar link without entries", () => {
        state.whatsNew = { entries: [], seenAt: null };
        expect(link(mountLayout()).exists()).toBe(false);
    });
});
