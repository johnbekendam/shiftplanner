import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import ColorTokenTable from "@/pages/themeBuilder/ColorTokenTable.vue";
import ColorTokenPicker from "@/components/ui/ColorTokenPicker.vue";

const colors = {
    a_bg: { light: "white", dark: "zinc-800" },
    a_text: { light: "zinc-700", dark: "zinc-300" },
    a_border: { light: "zinc-200", dark: "zinc-700" },
};

const mountTable = (props) =>
    mount(ColorTokenTable, {
        props: {
            columns: ["Background", "Text", "Border"],
            rows: [{ label: "Row A", keys: ["a_bg", "a_text", "a_border"] }],
            colors,
            mode: "light",
            ...props,
        },
    });

describe("ColorTokenTable", () => {
    it("leaves the top-left header cell empty when no label is given", () => {
        const w = mountTable();
        expect(w.findAll("thead th")[0].text()).toBe("");
    });

    it("puts the label in the top-left header cell", () => {
        const w = mountTable({ label: "Tabs" });
        expect(w.findAll("thead th")[0].text()).toBe("Tabs");
    });

    it("renders a blank cell for a null key and one fewer picker", () => {
        const w = mountTable({
            rows: [{ label: "Bar", keys: ["a_bg", null, "a_border"] }],
        });
        const cells = w.findAll("tbody td");
        expect(cells).toHaveLength(3);
        expect(cells[1].findComponent(ColorTokenPicker).exists()).toBe(false);
        expect(cells[1].text()).toBe("");
        expect(w.findAllComponents(ColorTokenPicker)).toHaveLength(2);
    });
});
