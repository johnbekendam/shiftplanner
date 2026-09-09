import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "signup.page_title": "Request your personal link",
    "signup.card_title": "Request your personal link",
    "signup.intro": "Fill in your details and we will email you the link.",
    "signup.field.first_name": "First name",
    "signup.field.last_name": "Last name",
    "signup.field.email": "Email",
    "signup.submit": "Send me the link",
};

const form = reactive({
    first_name: "",
    last_name: "",
    email: "",
    errors: {},
    processing: false,
    calls: [],
    reset() {},
    post(url, opts) {
        form.calls.push(url);
        opts?.onFinish?.();
    },
});

const pageProps = reactive({ props: { flash: {} } });

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    useForm: () => form,
    usePage: () => pageProps,
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import Signup from "@/pages/Signup.vue";

const stubs = { CenteredLayout: { template: "<div><slot name='title' /><slot /></div>" } };
const mountSignup = () => mount(Signup, { global: { stubs } });

beforeEach(() => {
    form.calls = [];
    form.errors = {};
    form.first_name = "";
    form.last_name = "";
    form.email = "";
    pageProps.props.flash = {};
});

describe("Signup", () => {
    it("shows the three fields and the submit button", () => {
        const w = mountSignup();
        expect(w.text()).toContain("First name");
        expect(w.text()).toContain("Last name");
        expect(w.find('input[inputmode="email"]').exists()).toBe(true);
        expect(w.findAll("button").some((b) => b.text() === "Send me the link")).toBe(true);
    });

    it("posts to /signup on submit", async () => {
        const w = mountSignup();
        await w.find("form").trigger("submit");
        expect(form.calls).toContain("/signup");
    });

    it("replaces the form with the confirmation once flash.success is set", () => {
        pageProps.props.flash = { success: "If that address is valid, we sent your personal link." };
        const w = mountSignup();
        expect(w.find("form").exists()).toBe(false);
        expect(w.text()).toContain("If that address is valid");
    });
});
