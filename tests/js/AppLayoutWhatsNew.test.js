import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = { "nav.whats_new": "What's new", "whats_new.unseen": ":count unseen changes" };

const state = vi.hoisted(() => ({ whatsNew: null, url: "/dashboard" }));

vi.mock("@inertiajs/vue3", () => ({
    router: { post: vi.fn() },
    usePage: () => ({
        props: { translations: en, auth: { user: { id: 1, role: "manager" } }, whatsNew: state.whatsNew },
        url: state.url,
    }),
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

import AppLayout from "@/layouts/AppLayout.vue";

const stubs = {
    Layout: { template: "<div><slot name='sidebar' /><slot /></div>" },
    AppLogo: true,
    FlashMessage: true,
};

const mountLayout = () => mount(AppLayout, { global: { stubs } });
const link = (w) => w.find('[data-testid="whats-new-link"]');
const badge = (w) => w.find('[data-testid="whats-new-badge"]');

beforeEach(() => {
    state.whatsNew = null;
    state.url = "/dashboard";
});

describe("AppLayout What's new", () => {
    it("links to the What's new page with the unseen count as a badge", () => {
        state.whatsNew = { unseen: 2, hasEntries: true };
        const w = mountLayout();

        expect(link(w).attributes("href")).toBe("/whats-new");
        expect(link(w).text()).toContain("What's new");
        expect(badge(w).text()).toBe("2");
        expect(badge(w).attributes("aria-label")).toBe("2 unseen changes");
    });

    it("shows no badge without unseen entries", () => {
        state.whatsNew = { unseen: 0, hasEntries: true };
        const w = mountLayout();

        expect(link(w).exists()).toBe(true);
        expect(badge(w).exists()).toBe(false);
    });

    it("hides the link without entries", () => {
        state.whatsNew = { unseen: 0, hasEntries: false };
        expect(link(mountLayout()).exists()).toBe(false);
    });

    it("opens no dialog on its own", () => {
        state.whatsNew = { unseen: 2, hasEntries: true };
        mountLayout();
        expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    });
});
