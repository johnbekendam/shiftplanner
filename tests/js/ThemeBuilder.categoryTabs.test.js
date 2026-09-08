import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { themeBuilderProps, en } from "./support/themeBuilderProps";

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    router: { post: () => {}, get: () => {}, visit: () => {} },
    usePage: () => ({ props: { translations: en, logoUrl: null } }),
}));

import ThemeBuilder from "@/pages/ThemeBuilder.vue";
import { PREVIEW_CATEGORIES } from "@/pages/themeBuilder/previewCategories";

function mountBuilder() {
    return mount(ThemeBuilder, { props: themeBuilderProps });
}

const tabs = (w) =>
    w.get('[data-testid="preview-category-tabs"]').findAll('button[role="tab"]');

describe("ThemeBuilder preview category tabs", () => {
    it("renders one tab per preview category, in order", () => {
        const w = mountBuilder();
        expect(tabs(w).map((b) => b.text())).toEqual([
            "Chrome",
            "Menu",
            "Text",
            "Buttons",
            "Tabs",
            "Table",
            "Forms",
            "Status & feedback",
            "Surface",
        ]);
    });

    it("selects Chrome by default", () => {
        const selected = tabs(mountBuilder()).filter(
            (b) => b.attributes("aria-selected") === "true",
        );
        expect(selected).toHaveLength(1);
        expect(selected[0].text()).toBe("Chrome");
    });

    it("moves selection on click", async () => {
        const w = mountBuilder();
        const formsIndex = PREVIEW_CATEGORIES.findIndex((c) => c.key === "forms");
        await tabs(w)[formsIndex].trigger("click");
        const selected = tabs(w).filter(
            (b) => b.attributes("aria-selected") === "true",
        );
        expect(selected).toHaveLength(1);
        expect(selected[0].text()).toBe("Forms");
    });

    it("swaps the frame content composition with the active category", async () => {
        const w = mountBuilder();
        // chrome is active by default -> the page-text specimen
        expect(w.text()).toContain("This is the page text.");

        const tabsIndex = PREVIEW_CATEGORIES.findIndex((c) => c.key === "tabs");
        await tabs(w)[tabsIndex].trigger("click");
        expect(w.text()).toContain("Records");
        expect(w.text()).not.toContain("This is the page text.");

        const formsIndex = PREVIEW_CATEGORIES.findIndex((c) => c.key === "forms");
        await tabs(w)[formsIndex].trigger("click");
        expect(w.text()).toContain("Edit profile");
    });

    it("colours the chrome page-text specimen with the content-text token", () => {
        const w = mountBuilder(); // chrome active
        const note = w
            .findAll("p")
            .find((p) => p.text() === "This is the page text.");
        expect(note.attributes("style")).toContain(
            "color: var(--color-content-text)",
        );
    });

    it("renders the frame overlay only for categories that have one", async () => {
        const w = mountBuilder();
        // chrome: no overlay
        expect(w.text()).not.toContain("Dialog title");

        const surfaceIndex = PREVIEW_CATEGORIES.findIndex(
            (c) => c.key === "surface",
        );
        await tabs(w)[surfaceIndex].trigger("click");
        expect(w.text()).toContain("Dialog title"); // modal over the frame

        const textIndex = PREVIEW_CATEGORIES.findIndex((c) => c.key === "text");
        await tabs(w)[textIndex].trigger("click");
        expect(w.text()).not.toContain("Dialog title");
    });

    it("shows the active category's picker set in its own card, headed by the tab name", async () => {
        const w = mountBuilder();
        const card = () => w.get('[data-testid="settings-panel"]');
        const panel = () => card().text();

        // its own card, separate from the example card
        expect(card().classes()).toContain("rounded-lg"); // Card root
        expect(
            card().element.contains(w.get("[data-theme-preview]").element),
        ).toBe(false);

        // Chrome: header names the tab, body has the chrome table + singles
        expect(panel()).toContain("Chrome");
        expect(panel()).toContain("Header");
        expect(panel()).toContain("Page");
        expect(panel()).toContain("Separator");
        expect(panel()).not.toContain("Overlay");

        const surfaceIndex = PREVIEW_CATEGORIES.findIndex(
            (c) => c.key === "surface",
        );
        await tabs(w)[surfaceIndex].trigger("click");
        expect(panel()).toContain("Surface");
        expect(panel()).toContain("Overlay");

        const statusIndex = PREVIEW_CATEGORIES.findIndex(
            (c) => c.key === "status",
        );
        await tabs(w)[statusIndex].trigger("click");
        expect(panel()).toContain("Badge success bg");
    });

    it("drives the frame breadcrumb: Home / <active category>", async () => {
        const w = mountBuilder();
        const crumb = () => w.get('[data-testid="frame-breadcrumb"]').text();

        expect(crumb()).toContain("Home");
        expect(crumb()).toContain("Chrome");

        const formsIndex = PREVIEW_CATEGORIES.findIndex((c) => c.key === "forms");
        await tabs(w)[formsIndex].trigger("click");

        expect(crumb()).toContain("Home");
        expect(crumb()).toContain("Forms");
        expect(crumb()).not.toContain("Chrome");
    });
});
