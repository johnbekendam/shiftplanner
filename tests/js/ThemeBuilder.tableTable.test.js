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
import ColorTokenTable from "@/pages/themeBuilder/ColorTokenTable.vue";
import { PREVIEW_CATEGORIES } from "@/pages/themeBuilder/previewCategories";

const mountBuilder = () => mount(ThemeBuilder, { props: themeBuilderProps });
const tabs = (w) =>
    w.get('[data-testid="preview-category-tabs"]').findAll('button[role="tab"]');
const clickTable = (w) =>
    tabs(w)[PREVIEW_CATEGORIES.findIndex((c) => c.key === "table")].trigger(
        "click",
    );

describe("ThemeBuilder Table page", () => {
    it("renders the Table table with the right shape", async () => {
        const w = mountBuilder();
        await clickTable(w);

        const table = w
            .get('[data-testid="settings-panel"]')
            .findComponent(ColorTokenTable);

        expect(table.props("label")).toBe("Table");
        expect(table.props("columns")).toEqual([
            "Background",
            "Text",
            "Separator",
        ]);
        expect(table.props("rows").map((r) => r.label)).toEqual([
            "Header",
            "Row",
            "Row hover",
            "Row selected",
        ]);
        // Row hover / Row selected have no Separator -> 10 pickers.
        expect(table.findAllComponents(ColorTokenPicker)).toHaveLength(10);
    });

    it("shows the pagination-accent tokens as plain rows below the table", async () => {
        const w = mountBuilder();
        await clickTable(w);
        const panel = w.get('[data-testid="settings-panel"]');

        expect(panel.text()).toContain("Pagination active bg");
        expect(panel.text()).toContain("Pagination active text");
        expect(panel.text()).not.toContain("Brand bg");
    });

    it("an edit in the table reaches the preview", async () => {
        const w = mountBuilder();
        await clickTable(w);
        const style = () => w.get("[data-theme-preview]").attributes("style");

        expect(style()).toContain(
            "--color-table-header-bg: var(--color-zinc-50)",
        );

        const table = w
            .get('[data-testid="settings-panel"]')
            .findComponent(ColorTokenTable);
        await table
            .findAllComponents(ColorTokenPicker)[0]
            .vm.$emit("update:modelValue", "red-500");

        expect(style()).toContain(
            "--color-table-header-bg: var(--color-red-500)",
        );
    });

    it("editing the pagination accent reaches the preview, border included", async () => {
        const w = mountBuilder();
        await clickTable(w);
        const style = () => w.get("[data-theme-preview]").attributes("style");

        expect(style()).toContain(
            "--color-pagination-active-bg: var(--color-brand-600)",
        );
        expect(style()).toContain(
            "--color-pagination-active-border: var(--color-pagination-active-bg)",
        );

        const panel = w.get('[data-testid="settings-panel"]');
        const pickers = panel.findAllComponents(ColorTokenPicker);
        // last two pickers on the page are the pagination accent rows (bg, text).
        await pickers[pickers.length - 2].vm.$emit("update:modelValue", "red-500");

        expect(style()).toContain(
            "--color-pagination-active-bg: var(--color-red-500)",
        );
    });
});
