import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import {
    compositionFor,
    overlayFor,
} from "@/pages/themeBuilder/compositions/registry";
import ChromeComposition from "@/pages/themeBuilder/compositions/ChromeComposition.vue";
import SurfaceComposition from "@/pages/themeBuilder/compositions/SurfaceComposition.vue";
import ButtonsComposition from "@/pages/themeBuilder/compositions/ButtonsComposition.vue";
import FormsComposition from "@/pages/themeBuilder/compositions/FormsComposition.vue";
import DataComposition from "@/pages/themeBuilder/compositions/DataComposition.vue";
import TabsComposition from "@/pages/themeBuilder/compositions/TabsComposition.vue";
import StatusComposition from "@/pages/themeBuilder/compositions/StatusComposition.vue";
import TextComposition from "@/pages/themeBuilder/compositions/TextComposition.vue";
import SurfaceOverlay from "@/pages/themeBuilder/compositions/SurfaceOverlay.vue";
import StatusOverlay from "@/pages/themeBuilder/compositions/StatusOverlay.vue";
import ButtonDanger from "@/components/ui/ButtonDanger.vue";
import Card from "@/components/ui/Card.vue";
import { PREVIEW_CATEGORIES } from "@/pages/themeBuilder/previewCategories";

const OWN = {
    chrome: ChromeComposition,
    menu: ChromeComposition,
    surface: SurfaceComposition,
    buttons: ButtonsComposition,
    forms: FormsComposition,
    tabs: TabsComposition,
    table: DataComposition,
    status: StatusComposition,
    text: TextComposition,
};

describe("composition registry", () => {
    it("maps categories with their own composition", () => {
        for (const [key, comp] of Object.entries(OWN)) {
            expect(compositionFor(key)).toBe(comp);
        }
    });

    it("has an own composition for every preview category", () => {
        for (const c of PREVIEW_CATEGORIES) {
            expect(OWN[c.key], c.key).toBeTruthy();
            expect(compositionFor(c.key)).toBe(OWN[c.key]);
        }
    });

    it("falls back to the Chrome note for an unknown key", () => {
        expect(compositionFor("nope")).toBe(ChromeComposition);
    });

    it("Surface composition renders the card screen (modal is an overlay)", () => {
        const w = mount(SurfaceComposition);
        expect(w.text()).toContain("Summary");
        expect(w.text()).toContain("Recent");
        // the modal lives in the frame overlay slot, not the composition
        expect(w.text()).not.toContain("Dialog title");
    });

    it("Buttons composition shows every variant in every state", () => {
        const w = mount(ButtonsComposition);
        const text = w.text();
        for (const label of ["Primary", "Secondary", "Danger"]) {
            expect(text).toContain(label);
        }
        // each variant renders Normal / Hover / Disabled buttons
        expect(w.findAllComponents(ButtonDanger)).toHaveLength(3);
        const dangerStates = w
            .findAllComponents(ButtonDanger)
            .map((b) => b.props("state"));
        expect(dangerStates).toEqual(["normal", "hover", "disabled"]);
        expect(text).not.toContain("Text link");
    });

    it("Forms composition renders a labelled form with state captions", () => {
        const w = mount(FormsComposition);
        const text = w.text();
        // every text input is labelled
        for (const label of ["Display name", "Email", "Account ID", "Bio", "Role"]) {
            expect(text).toContain(label);
        }
        // text input shown resting, invalid and disabled together
        for (const caption of ["Resting", "Invalid state", "Disabled"]) {
            expect(text).toContain(caption);
        }
        expect(text).toContain("Enter a valid email address");
        expect(w.html()).toContain("var(--color-input-invalid-border)");
    });

    it("Data composition shows table row states and pagination, no tab bar", () => {
        const w = mount(DataComposition);
        const html = w.html();
        expect(html).toContain("var(--color-table-row-hover-bg)");
        expect(html).toContain("var(--color-table-row-selected-bg)");
        expect(html).toContain("var(--color-pagination-active-bg)");
        expect(html).not.toContain("var(--color-tab-bg)");
    });

    it("Tabs composition puts the tab bar in a real Card's header", () => {
        const w = mount(TabsComposition);
        expect(w.text()).toContain("Active");
        expect(w.findComponent(Card).exists()).toBe(true);
        const html = w.html();
        expect(html).toContain("var(--color-tab-bg)");
        expect(html).toContain("var(--color-tab-active-bg)");
        expect(html).not.toContain("var(--color-table-row-bg)");
        expect(html).not.toContain("var(--color-pagination-active-bg)");
    });

    it("Status composition shows alerts, badges, a toast, a tooltip and progress", () => {
        const w = mount(StatusComposition);
        const text = w.text();
        for (const s of ["Success", "Warning", "Error", "Info"]) {
            expect(text).toContain(s);
        }
        expect(text).toContain("Standard");
        expect(text).toContain("Muted");
        expect(text.toLowerCase()).toContain("tooltip");
        expect(text.toLowerCase()).toContain("progress");
        expect(w.html()).toContain("var(--color-badge-success-bg)");
    });

    it("routes overlays: surface -> modal, status -> toast, others -> none", () => {
        expect(overlayFor("surface")).toBe(SurfaceOverlay);
        expect(overlayFor("status")).toBe(StatusOverlay);
        for (const key of ["chrome", "buttons", "forms", "tabs", "table", "text"]) {
            expect(overlayFor(key)).toBeNull();
        }
    });

    it("Surface overlay is a full-frame modal on the overlay token", () => {
        const w = mount(SurfaceOverlay);
        expect(w.text()).toContain("Dialog title");
        expect(w.html()).toContain("var(--color-overlay)");
    });

    it("Status overlay is a corner toast", () => {
        const w = mount(StatusOverlay);
        expect(w.text().toLowerCase()).toContain("toast");
        expect(w.html()).toMatch(/absolute/);
    });

    it("Text composition shows a specimen per text type", () => {
        const w = mount(TextComposition);
        const text = w.text();
        for (const label of ["Heading", "Normal text", "Muted text", "Link normal", "Link hover"]) {
            expect(text).toContain(label);
        }
        const html = w.html();
        expect(html).toContain("var(--color-text-link)");
        expect(html).toContain("var(--color-text-link-hover)");
        expect(text).not.toContain("Secondary text");
    });
});
