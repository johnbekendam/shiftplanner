import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import { en } from "./support/themeBuilderProps.js";

import { vi } from "vitest";
vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import RecipientPicker from "@/components/mailbox/RecipientPicker.vue";

const employees = [
    { id: 1, name: "Alice Ng", email: "alice@example.com" },
    { id: 2, name: "Bob Li", email: "bob@example.com" },
];

const users = [
    { id: 10, name: "Carl Ito", email: "carl@example.com" },
];

const mountPicker = (modelValue = { employee_ids: [], user_ids: [] }) =>
    mount(RecipientPicker, { props: { employees, users, modelValue } });

describe("RecipientPicker", () => {
    it("adds an employee via the + button", async () => {
        const w = mountPicker();
        await w.findAll("li button")[0].trigger("click");

        expect(w.emitted("update:modelValue")[0][0]).toEqual({ employee_ids: [1], user_ids: [] });
    });

    it("removing from the selected list updates the model", async () => {
        const w = mountPicker({ employee_ids: [1], user_ids: [] });
        const removeButtons = w.findAll("li button[aria-label='Remove']");
        await removeButtons[removeButtons.length - 1].trigger("click");

        expect(w.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [], user_ids: [] });
    });

    it("switching to the Users tab keeps prior employee selections", async () => {
        const w = mountPicker({ employee_ids: [1], user_ids: [] });
        await w.findAll("button").find((b) => b.text() === "Users").trigger("click");
        await w.findAll("li button")[0].trigger("click");

        expect(w.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [1], user_ids: [10] });
    });

    it("search filters the active source's list only", async () => {
        const w = mountPicker();
        await w.find("input[type='text'], input[type='search']").setValue("bob");

        const names = w.findAll("li .text-sm").map((el) => el.text());
        expect(names).toEqual(["Bob Li"]);
    });

    it("shows selections from both sources in the selected list", () => {
        const w = mountPicker({ employee_ids: [1], user_ids: [10] });
        const selectedText = w.findAll("ul")[1].text();

        expect(selectedText).toContain("Alice Ng");
        expect(selectedText).toContain("Carl Ito");
    });

    it("hides an already-selected person from the source list", () => {
        const w = mountPicker({ employee_ids: [1], user_ids: [] });
        const sourceNames = w.findAll("ul")[0].findAll(".text-sm").map((el) => el.text());

        expect(sourceNames).not.toContain("Alice Ng");
        expect(sourceNames).toContain("Bob Li");
    });

    it("Add all adds every visible person in the active source at once", async () => {
        const w = mountPicker();
        await w.findAll("button").find((b) => b.text() === "Add all").trigger("click");

        expect(w.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [1, 2], user_ids: [] });
    });

    it("Add all only adds the search-filtered people", async () => {
        const w = mountPicker();
        await w.find("input[type='text'], input[type='search']").setValue("bob");
        await w.findAll("button").find((b) => b.text() === "Add all").trigger("click");

        expect(w.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [2], user_ids: [] });
    });
});
