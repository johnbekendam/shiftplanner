import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const { pageProps } = vi.hoisted(() => ({ pageProps: { props: { flash: {} } } }));

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => pageProps,
}));

import FlashMessage from "@/components/FlashMessage.vue";

beforeEach(() => {
    pageProps.props = { flash: {} };
});

describe("FlashMessage", () => {
    it("stays silent on a successful action", () => {
        pageProps.props = { flash: { success: "Employee created." } };
        const w = mount(FlashMessage);
        expect(w.text()).toBe("");
        expect(w.find("div").exists()).toBe(false);
    });

    it("shows a problem in the error style", () => {
        pageProps.props = { flash: { error: "Something went wrong." } };
        const w = mount(FlashMessage);

        expect(w.text()).toContain("Something went wrong.");
        const cls = w.get("div").classes().join(" ");
        expect(cls).toContain("bg-(--color-badge-error-bg)");
        expect(cls).not.toContain("bg-(--color-badge-success-bg)");
    });

    it("renders nothing without a flash", () => {
        const w = mount(FlashMessage);
        expect(w.find("div").exists()).toBe(false);
    });
});
