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
const clickTabs = (w) =>
    tabs(w)[PREVIEW_CATEGORIES.findIndex((c) => c.key === "tabs")].trigger(
        "click",
    );

describe("ThemeBuilder Tabs page", () => {
    it("renders one Tabs table with the right shape", async () => {
        const w = mountBuilder();
        await clickTabs(w);

        const table = w
            .get('[data-testid="settings-panel"]')
            .findComponent(ColorTokenTable);

        expect(table.props("label")).toBe("Tabs");
        expect(table.props("columns")).toEqual(["Background", "Text", "Border"]);
        expect(table.props("rows").map((r) => r.label)).toEqual([
            "Bar",
            "Normal",
            "Hover",
            "Active",
        ]);
        expect(table.find("thead th").text()).toBe("Tabs");
        // Bar has no Text, Normal has no Border -> 10 pickers over 12 cells.
        expect(table.findAllComponents(ColorTokenPicker)).toHaveLength(10);
    });

    it("an edit reaches the preview", async () => {
        const w = mountBuilder();
        await clickTabs(w);
        const style = () => w.get("[data-theme-preview]").attributes("style");

        expect(style()).toContain("--color-tab-hover-bg: var(--color-zinc-50)");

        const table = w
            .get('[data-testid="settings-panel"]')
            .findComponent(ColorTokenTable);
        // pickers: Bar(bg, sep)=2, Normal(bg, text)=2, then Hover bg at index 4.
        await table
            .findAllComponents(ColorTokenPicker)[4]
            .vm.$emit("update:modelValue", "red-500");

        expect(style()).toContain("--color-tab-hover-bg: var(--color-red-500)");
    });
});
