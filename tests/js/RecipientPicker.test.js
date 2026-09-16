import { describe, it, expect, afterEach } from "vitest";
import { mount, DOMWrapper } from "@vue/test-utils";
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

let wrapper = null;

// The picker's browse/add UI lives in a <Teleport to="body"> modal, controlled
// by the `open` v-model (the trigger button lives in the parent page, not
// this component). Its content ends up as a sibling of the component root in
// <body>, not a descendant — so it's queried through a DOMWrapper over
// document.body, not through the component wrapper.
function mountOpenPicker(modelValue = { employee_ids: [], user_ids: [] }) {
    wrapper = mount(RecipientPicker, {
        attachTo: document.body,
        props: { employees, users, modelValue, open: true },
    });
    const modalEl = document.body.querySelector('[data-testid="recipient-picker-modal"]');
    return { wrapper, modal: new DOMWrapper(modalEl) };
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe("RecipientPicker", () => {
    it("adds an employee via the + button", async () => {
        const { modal } = mountOpenPicker();
        await modal.findAll("li button")[0].trigger("click");

        expect(wrapper.emitted("update:modelValue")[0][0]).toEqual({ employee_ids: [1], user_ids: [] });
    });

    it("removing from the selected list updates the model", async () => {
        mountOpenPicker({ employee_ids: [1], user_ids: [] });
        await wrapper.find("button[aria-label='Remove']").trigger("click");

        expect(wrapper.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [], user_ids: [] });
    });

    it("switching to the Users tab keeps prior employee selections", async () => {
        const { modal } = mountOpenPicker({ employee_ids: [1], user_ids: [] });
        await modal.findAll("button").find((b) => b.text() === "Users").trigger("click");
        await modal.findAll("li button")[0].trigger("click");

        expect(wrapper.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [1], user_ids: [10] });
    });

    it("search filters the active source's list only", async () => {
        const { modal } = mountOpenPicker();
        await modal.find("input[type='text'], input[type='search']").setValue("bob");

        const names = modal.findAll("li .text-sm").map((el) => el.text());
        expect(names).toEqual(["Bob Li"]);
    });

    it("shows selections from both sources in the selected list, as a name-only badge", async () => {
        mountOpenPicker({ employee_ids: [1], user_ids: [10] });
        // The selected list renders outside the modal, in the component's own root.
        const selectedText = wrapper.html();

        expect(selectedText).toContain("Alice Ng");
        expect(selectedText).toContain("Carl Ito");
        expect(selectedText).not.toContain("alice@example.com");
        expect(selectedText).not.toContain("Employee");
        expect(selectedText).not.toContain("User");
    });

    it("hides an already-selected person from the source list", async () => {
        const { modal } = mountOpenPicker({ employee_ids: [1], user_ids: [] });
        const sourceNames = modal.findAll("li .text-sm").map((el) => el.text());

        expect(sourceNames).not.toContain("Alice Ng");
        expect(sourceNames).toContain("Bob Li");
    });

    it("Add all adds every visible person in the active source at once", async () => {
        const { modal } = mountOpenPicker();
        await modal.findAll("button").find((b) => b.text() === "Add all").trigger("click");

        expect(wrapper.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [1, 2], user_ids: [] });
    });

    it("Add all only adds the search-filtered people", async () => {
        const { modal } = mountOpenPicker();
        await modal.find("input[type='text'], input[type='search']").setValue("bob");
        await modal.findAll("button").find((b) => b.text() === "Add all").trigger("click");

        expect(wrapper.emitted("update:modelValue").at(-1)[0]).toEqual({ employee_ids: [2], user_ids: [] });
    });

    it("the picker modal is closed by default", () => {
        wrapper = mount(RecipientPicker, {
            attachTo: document.body,
            props: { employees, users, modelValue: { employee_ids: [], user_ids: [] } },
        });

        expect(document.body.textContent).not.toContain("Recipient picker");
    });

    it("Done closes the modal", async () => {
        const { modal } = mountOpenPicker();
        expect(document.body.textContent).toContain("Recipient picker");

        await modal.findAll("button").find((b) => b.text() === "Done").trigger("click");

        expect(document.body.textContent).not.toContain("Recipient picker");
    });
});
