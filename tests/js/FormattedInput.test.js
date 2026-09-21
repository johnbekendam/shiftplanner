import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import NumberInput from "@/components/ui/Input/Number.vue";
import EmailInput from "@/components/ui/Input/Email.vue";

// FormattedInput (which NumberInput/EmailInput/PhoneInput/DateInput/TimeInput
// wrap) intercepts Backspace/Delete itself to keep the cursor off
// auto-inserted delimiters, bypassing the native `input` event entirely. The
// `live` emit used to live only in that `input` handler, so deleting a
// character — including deleting down to empty — never updated the model
// until blur, even with `live` set. These guard the fix.

describe("FormattedInput live mode — backspace/delete", () => {
    it("NumberInput emits on backspace, not just forward typing", async () => {
        const w = mount(NumberInput, { props: { modelValue: 40, min: 0, max: 48, step: 1, live: true } });
        const input = w.find("input");

        await input.trigger("keydown", { key: "Backspace" });

        expect(w.emitted("update:modelValue")).toBeTruthy();
        expect(w.emitted("update:modelValue").at(-1)[0]).toBe(4);
    });

    it("NumberInput emits null when backspacing the last digit to empty", async () => {
        const w = mount(NumberInput, { props: { modelValue: 4, min: 0, max: 48, step: 1, live: true } });
        const input = w.find("input");

        await input.trigger("keydown", { key: "Backspace" });

        expect(w.emitted("update:modelValue").at(-1)[0]).toBe(null);
    });

    it("EmailInput emits on backspace, and on deleting the last character", async () => {
        const w = mount(EmailInput, { props: { modelValue: "a", live: true } });
        const input = w.find("input");

        await input.trigger("keydown", { key: "Backspace" });

        expect(w.emitted("update:modelValue").at(-1)[0]).toBe("");
    });

    it("EmailInput does not emit live when the live prop is not set", async () => {
        const w = mount(EmailInput, { props: { modelValue: "a" } });
        const input = w.find("input");

        await input.trigger("keydown", { key: "Backspace" });

        expect(w.emitted("update:modelValue")).toBeFalsy();
    });

    it("EmailInput shows a placeholder on the input element", () => {
        const w = mount(EmailInput, { props: { modelValue: "" }, attrs: { placeholder: "name@example.com" } });

        expect(w.find("input").attributes("placeholder")).toBe("name@example.com");
    });

    it("EmailInput has no placeholder unless one is given", () => {
        const w = mount(EmailInput, { props: { modelValue: "" } });

        expect(w.find("input").attributes("placeholder")).toBe("");
    });
});
