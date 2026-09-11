import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "auth.expired.title": "This link is no longer valid",
    "auth.expired.invite": "This invite link has expired or was already used. Ask an admin to resend your invite.",
    "auth.expired.login": "This sign-in link has expired or was already used. Request a new one from the login page.",
    "auth.expired.unknown": "This link is not valid.",
    "auth.expired.back_to_login": "Back to login",
};

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import LinkExpired from "@/pages/Auth/LinkExpired.vue";

const stubs = { CenteredLayout: { template: "<div><slot name='title' /><slot /></div>" } };

describe("Auth/LinkExpired", () => {
    it("renders the invite-specific message", () => {
        const w = mount(LinkExpired, { props: { purpose: "invite" }, global: { stubs } });
        expect(w.find('[data-testid="expired-message"]').text()).toContain("Ask an admin to resend your invite.");
    });

    it("renders the login-specific message", () => {
        const w = mount(LinkExpired, { props: { purpose: "login" }, global: { stubs } });
        expect(w.find('[data-testid="expired-message"]').text()).toContain("Request a new one from the login page.");
    });

    it("falls back to the unknown message with no purpose", () => {
        const w = mount(LinkExpired, { props: { purpose: null }, global: { stubs } });
        expect(w.find('[data-testid="expired-message"]').text()).toBe("This link is not valid.");
    });

    it("links back to the login page", () => {
        const w = mount(LinkExpired, { props: { purpose: "login" }, global: { stubs } });
        const link = w.findAll("a").find((a) => a.text() === "Back to login");
        expect(link.attributes("href")).toBe("/login");
    });
});
