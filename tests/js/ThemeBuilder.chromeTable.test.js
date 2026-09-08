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
import ColorTokenPicker from "@/components/ui/ColorTokenPicker.vue";
import { PREVIEW_CATEGORIES } from "@/pages/themeBuilder/previewCategories";

const mountBuilder = () => mount(ThemeBuilder, { props: themeBuilderProps });
const tabs = (w) =>
    w.get('[data-testid="preview-category-tabs"]').findAll('button[role="tab"]');
const clickTab = (w, key) =>
    tabs(w)[PREVIEW_CATEGORIES.findIndex((c) => c.key === key)].trigger("click");

describe("ThemeBuilder Chrome table", () => {
    it("is a Background/Text table over Header, Sidebar, Page, then two separators", () => {
        const w = mountBuilder(); // chrome active by default
        const table = w.get('[data-testid="color-token-table"]');

        expect(table.findAll("thead th").map((t) => t.text())).toEqual([
            "",
            "Background",
            "Text",
        ]);
        expect(table.findAll("tbody th").map((t) => t.text())).toEqual([
            "Header",
            "Sidebar",
            "Page",
        ]);
        expect(table.findAllComponents(ColorTokenPicker)).toHaveLength(6);

        const panel = w.get('[data-testid="settings-panel"]');
        expect(panel.text()).toContain("Separator");
        expect(panel.text()).toContain("Horizontal (header)");
        expect(panel.text()).toContain("Vertical (sidebar)");
        // a checkbox per separator (2), plus a colour picker per separator (2 more pickers)
        expect(panel.findAll('input[type="checkbox"]')).toHaveLength(2);
        expect(panel.text()).not.toContain("Outer background");
    });

    it("toggling a separator enable flips its injected var between transparent and colour", async () => {
        const w = mountBuilder();
        const style = () => w.get("[data-theme-preview]").attributes("style");
        expect(style()).toContain("--color-separator-vertical: transparent");

        const boxes = w
            .get('[data-testid="settings-panel"]')
            .findAll('input[type="checkbox"]');
        await boxes[1].setValue(true); // vertical (sidebar)

        expect(style()).toContain(
            "--color-separator-vertical: var(--color-zinc-200)",
        );
        expect(style()).toContain("--color-separator-horizontal: transparent");
    });

    it("editing the header cell updates the preview, including the merged logo var", async () => {
        const w = mountBuilder();
        const style = () => w.get("[data-theme-preview]").attributes("style");
        expect(style()).toContain("--color-header-bg: var(--color-brand-800)");
        // logo is merged into header: derived, not its own token
        expect(style()).toContain("--color-logo-bg: var(--color-header-bg)");

        // first cell picker = header_bg (row Header, column Background)
        await w
            .get('[data-testid="color-token-table"]')
            .findAllComponents(ColorTokenPicker)[0]
            .vm.$emit("update:modelValue", "red-500");

        expect(style()).toContain("--color-header-bg: var(--color-red-500)");
        // the derivation itself doesn't change — it always points at header
        expect(style()).toContain("--color-logo-bg: var(--color-header-bg)");
    });

    it("non-table tabs fall back to the plain list", async () => {
        const w = mountBuilder();
        await clickTab(w, "forms");
        expect(w.find('[data-testid="color-token-table"]').exists()).toBe(false);
        expect(w.get('[data-testid="settings-panel"]').text()).toContain(
            "Input bg",
        );
    });
});
