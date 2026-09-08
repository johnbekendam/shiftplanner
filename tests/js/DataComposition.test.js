import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import DataComposition from "@/pages/themeBuilder/compositions/DataComposition.vue";
import Card from "@/components/ui/Card.vue";

describe("DataComposition", () => {
    it("has no tab bar — that lives in TabsComposition now", () => {
        const w = mount(DataComposition);
        expect(w.html()).not.toContain("--color-tab-bg");
    });

    it("puts the table and the pagination inside one Card", () => {
        const w = mount(DataComposition);
        const card = w.findComponent(Card);
        expect(card.exists()).toBe(true);
        expect(card.text()).toContain("1–15 of 97");
    });

    it("puts the pagination in the card's own footer slot", () => {
        const w = mount(DataComposition);
        const pagination = w.get('[data-testid="table-pagination"]');
        const footer = pagination.element.closest('[class*="rounded-b-lg"]');

        expect(footer).toBeTruthy();
        expect(footer.className).toContain("border-t");
    });

    it("colours every column with the row colour, no link styling", () => {
        const w = mount(DataComposition);
        expect(w.html()).not.toContain("--color-text-link");

        const actionCell = w
            .findAll("span")
            .find((s) => s.text() === "Edit");
        expect(actionCell.attributes("style")).toBeUndefined();
    });

    it("centers the Status and Action columns, header and row alike", () => {
        const w = mount(DataComposition);
        for (const text of ["Status", "Action", "Active", "Edit"]) {
            const cell = w.findAll("span").find((s) => s.text() === text);
            expect(cell.classes(), text).toContain("text-center");
        }
    });

    it("shows 8 rows", () => {
        const w = mount(DataComposition);
        const actionCells = w.findAll("span").filter((s) => s.text() === "Edit");
        expect(actionCells).toHaveLength(8);
    });

    it("shows the hover/selected note next to the name, not the action", () => {
        const w = mount(DataComposition);
        const text = w.text();

        expect(text).toContain("Record two (hover)");
        expect(text).toContain("Record three (selected)");

        const actionCells = w.findAll("span").filter((s) => s.text() === "Edit");
        for (const cell of actionCells) {
            expect(cell.text()).not.toContain("hover");
            expect(cell.text()).not.toContain("selected");
        }
    });
});
