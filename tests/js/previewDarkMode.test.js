import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import { themeBuilderProps, en } from "./support/themeBuilderProps";

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    router: { post: () => {}, get: () => {}, visit: () => {} },
    usePage: () => ({ props: { translations: en, logoUrl: null } }),
}));

import ThemeBuilder from "@/pages/ThemeBuilder.vue";

function mountBuilder() {
    return mount(ThemeBuilder, { props: themeBuilderProps });
}

beforeEach(() => document.documentElement.classList.remove("dark"));
afterEach(() => document.documentElement.classList.remove("dark"));

describe("preview light/dark switch", () => {
    it("re-injects dark values when the root gains the dark class", async () => {
        const w = mountBuilder();
        const style = () => w.get("[data-theme-preview]").attributes("style");

        expect(style()).toContain("--color-content-bg: white");
        expect(style()).toContain("--color-text-primary: var(--color-zinc-700)");

        document.documentElement.classList.add("dark");
        // let the MutationObserver callback run, then Vue update
        await new Promise((r) => setTimeout(r));
        await nextTick();

        expect(style()).toContain("--color-content-bg: var(--color-zinc-800)");
        expect(style()).not.toContain("--color-content-bg: white");
        expect(style()).toContain("--color-text-primary: var(--color-zinc-300)");
    });

    it("puts the light/dark toggle inside the example frame and flips on click", async () => {
        window.toggleDarkMode = () =>
            document.documentElement.classList.toggle("dark");

        const w = mountBuilder();
        const frame = w.get("[data-theme-preview]");
        const toggle = frame.get('[data-testid="dark-toggle"]');
        const style = () => frame.attributes("style");

        expect(style()).toContain("--color-content-bg: white");

        await toggle.trigger("click");
        await new Promise((r) => setTimeout(r));
        await nextTick();

        expect(document.documentElement.classList.contains("dark")).toBe(true);
        expect(style()).toContain("--color-content-bg: var(--color-zinc-800)");

        delete window.toggleDarkMode;
    });
});
