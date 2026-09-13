import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "app.save": "Save",
    "app.saving": "Saving…",
    "app.saved": "Saved",
    "app.cancel": "Cancel",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import TabSaveBar from "@/components/ui/TabSaveBar.vue";

const findButton = (w, text) => w.findAll("button").find((b) => b.text() === text);

describe("TabSaveBar", () => {
    it("shows Cancel and Save, both disabled when not dirty", () => {
        const w = mount(TabSaveBar);
        expect(findButton(w, "Cancel").attributes("disabled")).toBeDefined();
        expect(findButton(w, "Save").attributes("disabled")).toBeDefined();
    });

    it("enables both when dirty", () => {
        const w = mount(TabSaveBar, { props: { dirty: true } });
        expect(findButton(w, "Cancel").attributes("disabled")).toBeUndefined();
        expect(findButton(w, "Save").attributes("disabled")).toBeUndefined();
    });

    it("disables both while saving, even if dirty", () => {
        const w = mount(TabSaveBar, { props: { dirty: true, saving: true } });
        expect(findButton(w, "Cancel").attributes("disabled")).toBeDefined();
        expect(w.findAll("button").find((b) => b.text() === "Saving…").attributes("disabled")).toBeDefined();
    });

    it("shows Saved when justSaved is true", () => {
        const w = mount(TabSaveBar, { props: { justSaved: true } });
        expect(w.text()).toContain("Saved");
    });

    it("emits save and cancel on click", async () => {
        const w = mount(TabSaveBar, { props: { dirty: true } });
        await findButton(w, "Save").trigger("click");
        await findButton(w, "Cancel").trigger("click");
        expect(w.emitted("save")).toHaveLength(1);
        expect(w.emitted("cancel")).toHaveLength(1);
    });
});
