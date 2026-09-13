import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "shifts.note_label": "Information for employees",
    "shifts.note_hint": "Markdown. Shown at the top of every availability page.",
};

const { putSpy } = vi.hoisted(() => ({ putSpy: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) => {
        const form = reactive({
            ...initial,
            errors: {},
            processing: false,
            _defaults: { ...initial },
            get isDirty() {
                return Object.keys(initial).some((k) => this[k] !== this._defaults[k]);
            },
            defaults() {
                this._defaults = Object.fromEntries(Object.keys(initial).map((k) => [k, this[k]]));
            },
            reset() {
                Object.assign(this, this._defaults);
            },
            clearErrors() {
                this.errors = {};
            },
            put(url, opts) {
                putSpy(url, { ...this }, opts);
                opts?.onSuccess?.();
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

    it("submits the note to the shift note endpoint via the exposed submit()", async () => {
        const w = mount(ShiftNoteForm, { props: { note: "" } });
        w.findComponent(MultilineInput).vm.$emit("update:modelValue", "Updated note");
        await w.vm.$nextTick();

        await w.vm.submit();

        expect(putSpy).toHaveBeenCalledTimes(1);
        const [url, data] = putSpy.mock.calls[0];
        expect(url).toBe("/settings/shifts/note");
        expect(data).toMatchObject({ note: "Updated note" });
    });

    it("exposes isDirty reflecting the current edit, and cancel() resets it", async () => {
        const w = mount(ShiftNoteForm, { props: { note: "Original" } });
        expect(w.vm.isDirty).toBe(false);

        w.findComponent(MultilineInput).vm.$emit("update:modelValue", "Changed");
        await w.vm.$nextTick();
        expect(w.vm.isDirty).toBe(true);

        w.vm.cancel();
        await w.vm.$nextTick();
        expect(w.vm.isDirty).toBe(false);
        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("Original");
    });
});
