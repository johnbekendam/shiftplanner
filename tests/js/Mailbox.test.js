import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, DOMWrapper } from "@vue/test-utils";
import { reactive } from "vue";
import { en } from "./support/themeBuilderProps.js";

const { router, postSpy } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    postSpy: vi.fn(),
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
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
            post(...args) {
                const data = this._transform ? this._transform({ ...this }) : { ...this };
                postSpy(args[0], data);
                args[1]?.onSuccess?.();
            },
        });
        return form;
    },
}));

import Mailbox from "@/pages/Mailbox.vue";
import { TextInput, MultilineInput } from "@/components/ui/Input";

const compose = {
    types: [
        { value: "personal_page_link", label: "Personal page link" },
        { value: "custom", label: "Custom message" },
    ],
    type: "personal_page_link",
    template: { subject: "Your page", body: "Hi :name, :link" },
    employees: [
        { id: 1, name: "Alice Ng", email: "alice@example.com" },
        { id: 2, name: "Bob Li", email: "bob@example.com" },
    ],
    users: [{ id: 10, name: "Carl Ito", email: "carl@example.com" }],
    preselected_employee_id: null,
    unresolved_recipients: null,
    placeholder_tokens: [":name", ":link"],
};

const mountCompose = (overrides = {}) =>
    mount(Mailbox, {
        props: {
            messages: null,
            tab: "compose",
            search: "",
            counts: {},
            compose: { ...compose, ...overrides },
        },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
    });

// RecipientPicker's browse/add UI lives in a <Teleport to="body"> modal,
// opened via its icon-only "Add recipients" button (aria-label only, no
// visible text). The teleported content ends up as a sibling of the
// wrapper's own root in <body>, so it's queried through a DOMWrapper over
// that node rather than through the component wrapper.
async function openRecipientPicker(w) {
    await w.find("button[aria-label='Add recipients']").trigger("click");
    // Earlier tests in this file don't unmount, so a stale modal from a
    // previous mountCompose() can still be sitting in <body> — Teleport
    // appends, so this instance's node is always the last match.
    const nodes = document.body.querySelectorAll('[data-testid="recipient-picker-modal"]');
    return new DOMWrapper(nodes[nodes.length - 1]);
}

beforeEach(() => {
    router.get.mockReset();
    router.post.mockReset();
    router.put.mockReset();
    postSpy.mockReset();
});

describe("Mailbox — Compose tab", () => {
    it("seeds subject and body from the template", () => {
        const w = mountCompose();
        expect(w.findComponent(TextInput).props("modelValue")).toBe("Your page");
        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("Hi :name, :link");
    });

    it("preselects the employee from the query prop", () => {
        const w = mountCompose({ preselected_employee_id: 2 });
        expect(w.text()).toContain("Bob Li");
        expect(w.text()).not.toContain("Alice Ng");
    });

    it("always shows the recipients section, even with nobody selected", () => {
        const w = mountCompose();
        expect(w.text()).toContain("Recipients");
        expect(w.findAll("hr")).toHaveLength(1);
    });

    it("shows the recipients validation error even with nobody selected", () => {
        const w = mountCompose();
        w.vm.composeForm.errors.employee_ids = "Select at least one recipient.";
        return w.vm.$nextTick().then(() => {
            expect(w.text()).toContain("Select at least one recipient.");
        });
    });

    it("Create drafts posts the typed payload in draft mode", async () => {
        const w = mountCompose({ preselected_employee_id: 1 });
        await w.findAll("button").find((b) => b.text() === "Create drafts").trigger("click");

        expect(postSpy).toHaveBeenCalledTimes(1);
        const [url, data] = postSpy.mock.calls[0];
        expect(url).toBe("/mailbox/compose");
        expect(data).toMatchObject({
            type: "personal_page_link",
            subject: "Your page",
            body: "Hi :name, :link",
            employee_ids: [1],
            send_mode: "draft",
        });
    });

    it("Send now posts in queue mode", async () => {
        const w = mountCompose({ preselected_employee_id: 1 });
        await w.findAll("button").find((b) => b.text() === "Send now").trigger("click");

        expect(postSpy.mock.calls[0][1].send_mode).toBe("queue");
    });

    it("Save is hidden until the template is edited, then PUTs the current subject and body", async () => {
        const w = mountCompose();
        const saveButton = () => w.findAll("button").find((b) => b.text() === "Save");

        expect(saveButton()).toBeUndefined();

        await w.findComponent(TextInput).setValue("Edited subject");
        expect(saveButton()).toBeDefined();

        await saveButton().trigger("click");

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, data] = router.put.mock.calls[0];
        expect(url).toBe("/mailbox/templates/personal_page_link");
        expect(data).toEqual({ subject: "Edited subject", body: "Hi :name, :link" });
    });

    it("Save hides again once the save succeeds", async () => {
        const w = mountCompose();
        await w.findComponent(TextInput).setValue("Edited subject");
        await w.findAll("button").find((b) => b.text() === "Save").trigger("click");

        router.put.mock.calls[0][2].onSuccess();
        router.put.mock.calls[0][2].onFinish();
        await w.vm.$nextTick();

        expect(w.findAll("button").find((b) => b.text() === "Save")).toBeUndefined();
    });

    it("Cancel only appears once edited, and reverts the subject/body without saving", async () => {
        const w = mountCompose();
        const cancelButton = () => w.findAll("button").find((b) => b.text() === "Cancel");

        expect(cancelButton()).toBeUndefined();

        await w.findComponent(TextInput).setValue("Edited subject");
        expect(cancelButton()).toBeDefined();

        await cancelButton().trigger("click");

        expect(w.findComponent(TextInput).props("modelValue")).toBe("Your page");
        expect(w.findAll("button").find((b) => b.text() === "Cancel")).toBeUndefined();
        expect(router.put).not.toHaveBeenCalled();
    });

    it("adding an employee via the picker's + button adds them to employee_ids", async () => {
        const w = mountCompose();
        const modal = await openRecipientPicker(w);
        await modal.findAll("li button")[0].trigger("click");

        await w.findAll("button").find((b) => b.text() === "Create drafts").trigger("click");
        expect(postSpy.mock.calls[0][1].employee_ids).toEqual([1]);
    });

    it("disables Preview when the subject or body is empty", async () => {
        const w = mountCompose({
            type: "custom",
            template: { subject: "", body: "" },
        });
        const preview = () => w.findAll("button").find((b) => b.text() === "Preview");

        expect(preview().attributes("disabled")).toBeDefined();

        await w.findComponent(TextInput).setValue("Subject");
        expect(preview().attributes("disabled")).toBeDefined();

        await w.findComponent(MultilineInput).setValue("Body");
        expect(preview().attributes("disabled")).toBeUndefined();
    });

    it("clicking a placeholder token appends it to the body", async () => {
        const w = mountCompose();
        await w.findAll("button").find((b) => b.text() === ":link").trigger("click");

        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("Hi :name, :link :link");
    });

    it("clicking Button inserts a :button[label](url) snippet on its own line", async () => {
        const w = mountCompose();
        await w.findAll("button").find((b) => b.text() === "Button").trigger("click");

        expect(w.findComponent(MultilineInput).props("modelValue")).toBe("Hi :name, :link\n\n:button[label](url)");
    });

    it("Custom type has no fixed template and sends to selected users", async () => {
        const w = mountCompose({
            type: "custom",
            template: { subject: "", body: "" },
            preselected_employee_id: null,
        });
        await w.findComponent(TextInput).setValue("Heads up");
        await w.findComponent(MultilineInput).setValue("Hi :name");

        const modal = await openRecipientPicker(w);
        await modal.findAll("button").find((b) => b.text() === "Users").trigger("click");
        await modal.findAll("li button")[0].trigger("click");
        await w.findAll("button").find((b) => b.text() === "Create drafts").trigger("click");

        const [, data] = postSpy.mock.calls[0];
        expect(data).toMatchObject({ type: "custom", subject: "Heads up", body: "Hi :name", user_ids: [10] });
    });

    it("shows the unresolved-recipients dialog when the server flags recipients, and Continue resubmits with exclude_unresolved", async () => {
        // The server flashes `unresolved_recipients` into the compose payload on
        // the page it redirects back to; simulate that starting state directly.
        // The dialog uses <Teleport to="body">, so it renders outside the wrapper.
        const w = mount(Mailbox, {
            attachTo: document.body,
            props: {
                messages: null,
                tab: "compose",
                search: "",
                counts: {},
                compose: {
                    ...compose,
                    preselected_employee_id: 1,
                    unresolved_recipients: [{ name: "Dana", email: "dana@example.com", tokens: [":link"] }],
                },
            },
            global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
        });

        expect(document.body.textContent).toContain("Dana");

        const buttons = Array.from(document.body.querySelectorAll("button"));
        buttons.find((b) => b.textContent.trim() === "Continue without them").dispatchEvent(new MouseEvent("click", { bubbles: true }));
        await new Promise((r) => setTimeout(r));

        expect(postSpy).toHaveBeenCalledTimes(1);
        const [, data] = postSpy.mock.calls[0];
        expect(data).toMatchObject({ send_mode: "draft", exclude_unresolved: true });
        w.unmount();
    });

    it("Cancel on the unresolved-recipients dialog closes it without submitting", async () => {
        const w = mount(Mailbox, {
            attachTo: document.body,
            props: {
                messages: null,
                tab: "compose",
                search: "",
                counts: {},
                compose: {
                    ...compose,
                    unresolved_recipients: [{ name: "Dana", email: "dana@example.com", tokens: [":link"] }],
                },
            },
            global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
        });

        const buttons = Array.from(document.body.querySelectorAll("button"));
        buttons.find((b) => b.textContent.trim() === "Cancel").dispatchEvent(new MouseEvent("click", { bubbles: true }));
        await new Promise((r) => setTimeout(r));

        expect(document.body.textContent).not.toContain("Dana");
        expect(postSpy).not.toHaveBeenCalled();
        w.unmount();
    });
});

describe("Mailbox — tab switching", () => {
    it("does not preserve state when switching to the compose tab", async () => {
        const w = mount(Mailbox, {
            props: { messages: { data: [] }, tab: "draft", search: "", counts: {}, compose: null },
            global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
        });

        await w.findAll("button").find((b) => b.text().startsWith("Compose")).trigger("click");

        expect(router.get).toHaveBeenCalledTimes(1);
        const [url, params, options] = router.get.mock.calls[0];
        expect(url).toBe("/mailbox");
        expect(params).toEqual({ tab: "compose" });
        expect(options.preserveState).not.toBe(true);
    });

    it("preserves state when switching between list tabs", async () => {
        const w = mount(Mailbox, {
            props: { messages: { data: [] }, tab: "draft", search: "", counts: {}, compose: null },
            global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
        });

        await w.findAll("button").find((b) => b.text().startsWith("Sent")).trigger("click");

        const [, params, options] = router.get.mock.calls[0];
        expect(params).toEqual({ tab: "sent" });
        expect(options.preserveState).toBe(true);
    });
});

describe("Mailbox — message list", () => {
    const messages = {
        data: [
            {
                id: 7,
                recipient_name: "Alice Ng",
                recipient_email: "alice@example.com",
                subject: "Your page",
                status: "draft",
                body_html: "<p>hi</p>",
                composed_by: "Admin One",
            },
        ],
    };

    it("shows a Composed by column with the author name", () => {
        const w = mount(Mailbox, {
            props: { messages, tab: "draft", search: "", counts: { draft: 1 }, compose: null },
            global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
        });

        const headers = w.findAll("thead th").map((th) => th.text());
        expect(headers).toContain("Composed by");
        expect(w.find("tbody tr").text()).toContain("Admin One");
    });
});
