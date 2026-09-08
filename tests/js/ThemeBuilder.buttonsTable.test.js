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
const clickTab = (w, key) =>
    w
        .get('[data-testid="preview-category-tabs"]')
        .findAll('button[role="tab"]')
        [PREVIEW_CATEGORIES.findIndex((c) => c.key === key)].trigger("click");

describe("ThemeBuilder Buttons tab", () => {
    it("is a 9-row bg/text/border table over the three variants and states", async () => {
        const w = mountBuilder();
        await clickTab(w, "buttons");
        const table = w.get('[data-testid="color-token-table"]');

        expect(table.findAll("thead th").map((t) => t.text())).toEqual([
            "",
            "Background",
            "Text",
            "Border",
        ]);
        expect(table.findAll("tbody th").map((t) => t.text())).toEqual([
            "Primary",
            "Primary (hover)",
            "Primary (disabled)",
            "Secondary",
            "Secondary (hover)",
            "Secondary (disabled)",
            "Danger",
            "Danger (hover)",
            "Danger (disabled)",
        ]);
        expect(table.findAllComponents(ColorTokenPicker)).toHaveLength(27);
    });

    it("edits btn_danger_disabled_bg from the last row's first cell", async () => {
        const w = mountBuilder();
        await clickTab(w, "buttons");
        const style = () => w.get("[data-theme-preview]").attributes("style");
        expect(style()).toContain(
            "--color-btn-danger-disabled-bg: var(--color-zinc-100)",
        );

        // row 8 (Danger Disabled) col 0 (Background) => picker index 24
        await w
            .get('[data-testid="color-token-table"]')
            .findAllComponents(ColorTokenPicker)[24]
            .vm.$emit("update:modelValue", "rose-200");

        expect(style()).toContain(
            "--color-btn-danger-disabled-bg: var(--color-rose-200)",
        );
    });

    it("keeps the pagination accent off the Buttons tab and on the Table tab", async () => {
        const w = mountBuilder();
        await clickTab(w, "buttons");
        expect(w.get('[data-testid="settings-panel"]').text()).not.toContain(
            "Pagination active bg",
        );
        await clickTab(w, "table");
        expect(w.get('[data-testid="settings-panel"]').text()).toContain(
            "Pagination active bg",
        );
    });
});
