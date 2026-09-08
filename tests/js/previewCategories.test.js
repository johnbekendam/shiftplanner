import { describe, it, expect } from "vitest";
import {
    PREVIEW_CATEGORIES,
    DEFAULT_PREVIEW_CATEGORY,
} from "@/pages/themeBuilder/previewCategories";

describe("previewCategories", () => {
    it("lists the nine categories in order", () => {
        expect(PREVIEW_CATEGORIES.map((c) => c.key)).toEqual([
            "chrome",
            "menu",
            "text",
            "buttons",
            "tabs",
            "table",
            "forms",
            "status",
            "surface",
        ]);
    });

    it("gives every category an i18n label key", () => {
        for (const c of PREVIEW_CATEGORIES) {
            expect(c.labelKey).toBe(`theme.category.${c.key}`);
        }
    });

    it("defaults to chrome", () => {
        expect(DEFAULT_PREVIEW_CATEGORY).toBe("chrome");
    });
});
