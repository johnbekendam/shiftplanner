import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
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
import { CheckboxInput, TextInput, MultilineInput } from "@/components/ui/Input";

const compose = {
    types: [{ value: "personal_page_link", label: "Personal page link" }],
    type: "personal_page_link",
    template: { subject: "Your page", body: "Hi :name, :link" },
    employees: [
        { id: 1, name: "Alice Ng", email: "alice@example.com" },
        { id: 2, name: "Bob Li", email: "bob@example.com" },
    ],
    preselected_employee_id: null,
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
        const boxes = w.findAllComponents(CheckboxInput);
        // first checkbox is Alice, second is Bob
        expect(boxes[1].props("modelValue")).toBe(true);
        expect(boxes[0].props("modelValue")).toBe(false);
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

    it("Save template PUTs the current subject and body for the type", async () => {
        const w = mountCompose();
        await w.findAll("button").find((b) => b.text() === "Save template").trigger("click");

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, data] = router.put.mock.calls[0];
        expect(url).toBe("/mailbox/templates/personal_page_link");
        expect(data).toEqual({ subject: "Your page", body: "Hi :name, :link" });
    });

    it("ticking an employee adds them to employee_ids", async () => {
        const w = mountCompose();
        const aliceBox = w.findAllComponents(CheckboxInput)[0];
        await aliceBox.find("input").setValue(true);

        await w.findAll("button").find((b) => b.text() === "Create drafts").trigger("click");
        expect(postSpy.mock.calls[0][1].employee_ids).toEqual([1]);
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
