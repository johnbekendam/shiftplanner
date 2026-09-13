import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "shifts.schedule_note_label": "Shift schedule notes",
    "shifts.schedule_note_hint": "Markdown. Shown below the weekly availability grid.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import ScheduleNoteForm from "@/components/ScheduleNoteForm.vue";
import { MultilineInput } from "@/components/ui/Input";

describe("ScheduleNoteForm", () => {
    it("seeds the textarea from the note prop", () => {
        const w = mount(ScheduleNoteForm, { props: { note: "Friday ends at 20:00" } });
        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("Friday ends at 20:00");
    });

    it("defaults to an empty textarea when no note is set", () => {
        const w = mount(ScheduleNoteForm, { props: { note: "" } });
        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("");
    });

    it("emits update:note on change, without a network call", async () => {
        const w = mount(ScheduleNoteForm, { props: { note: "" } });
        w.findComponent(MultilineInput).vm.$emit("update:modelValue", "Updated note");
        await w.vm.$nextTick();

        expect(w.emitted("update:note")).toEqual([["Updated note"]]);
    });

    it("shows a validation error passed via the error prop", () => {
        const w = mount(ScheduleNoteForm, { props: { note: "", error: "Too long." } });
        expect(w.text()).toContain("Too long.");
    });
});
