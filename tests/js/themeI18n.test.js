import { describe, it, expect } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { PREVIEW_CATEGORIES } from "@/pages/themeBuilder/previewCategories";

const en = JSON.parse(
    readFileSync(resolve(process.cwd(), "resources/lang/en.json"), "utf8"),
);

describe("theme builder i18n", () => {
    it("has a label for every preview category", () => {
        for (const c of PREVIEW_CATEGORIES) {
            expect(en[c.labelKey], c.labelKey).toBeTruthy();
        }
    });

    it("names the status category 'Status & feedback'", () => {
        expect(en["theme.category.status"]).toBe("Status & feedback");
    });

    it("has the breadcrumb home label", () => {
        expect(en["theme.breadcrumb.home"]).toBe("Home");
    });

    it("has the chrome category note (a page-text specimen)", () => {
        expect(en["theme.preview.chrome_note"]).toBe("This is the page text.");
    });
});
