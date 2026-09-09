import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "shifts.note_label": "Shift information",
    "shifts.note_hint": "Markdown. Shown at the top of every availability page.",
    "shifts.note_save": "Save information",
};

const { putSpy } = vi.hoisted(() => ({ putSpy: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) => {
        const form = reactive({
            ...initial,
            errors: {},
            processing: false,
            transform(cb) {
                this._transform = cb;
                return this;
            },
            put(...args) {
                const data = this._transform ? this._transform({ ...initial, ...this }) : { ...this };
                putSpy(args[0], data, args[1]);
            },
        });
        return form;
    },
}));

import ShiftNoteForm from "@/components/ShiftNoteForm.vue";
import { MultilineInput } from "@/components/ui/Input";

beforeEach(() => putSpy.mockReset());

describe("ShiftNoteForm", () => {
    it("seeds the textarea from the note prop", () => {
        const w = mount(ShiftNoteForm, { props: { note: "# Allowances" } });
        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("# Allowances");
    });

    it("defaults to an empty textarea when no note is set", () => {
        const w = mount(ShiftNoteForm, { props: { note: "" } });
        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("");
    });

    it("submits the note to the shift note endpoint", async () => {
        const w = mount(ShiftNoteForm, { props: { note: "" } });
        w.findComponent(MultilineInput).vm.$emit("update:modelValue", "Updated note");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(putSpy).toHaveBeenCalledTimes(1);
        const [url, data] = putSpy.mock.calls[0];
        expect(url).toBe("/settings/shifts/note");
        expect(data).toMatchObject({ note: "Updated note" });
    });
});
