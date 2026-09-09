import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import ShiftNote from "@/components/ShiftNote.vue";

describe("ShiftNote", () => {
    it("renders the provided HTML", () => {
        const w = mount(ShiftNote, {
            props: { html: "<table><tr><td>Early</td></tr></table>" },
        });
        expect(w.find('[data-testid="shift-note"]').exists()).toBe(true);
        expect(w.find("table").exists()).toBe(true);
        expect(w.text()).toContain("Early");
    });

    it("renders nothing when html is null", () => {
        const w = mount(ShiftNote, { props: { html: null } });
        expect(w.find('[data-testid="shift-note"]').exists()).toBe(false);
        expect(w.text()).toBe("");
    });

    it("renders nothing when html is an empty string", () => {
        const w = mount(ShiftNote, { props: { html: "" } });
        expect(w.find('[data-testid="shift-note"]').exists()).toBe(false);
    });
});
