import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "auth.page_title": "ShiftPlanner",
    "auth.tab.signin": "Sign in",
    "auth.tab.personal_link": "Get my link",
    "auth.field.email": "Email",
    "auth.field.password": "Password",
    "auth.action.signin": "Sign in",
    "auth.action.request_link": "Email me a login link",
    "signup.intro": "Fill in your details and we will email you the link.",
    "signup.field.first_name": "First name",
    "signup.field.last_name": "Last name",
    "signup.field.email": "Email",
    "signup.submit": "Send me the link",
};

const loginForm = reactive({
    email: "",
    password: "",
    errors: {},
    processing: false,
    calls: [],
    reset() {},
    clearErrors() {},
    post(url, opts) {
        loginForm.calls.push(url);
        opts?.onSuccess?.();
        opts?.onFinish?.();
    },
});

const signupForm = reactive({
    first_name: "",
    last_name: "",
    email: "",
    errors: {},
    processing: false,
    calls: [],
    reset() {},
    clearErrors() {},
    post(url, opts) {
        signupForm.calls.push(url);
        opts?.onFinish?.();
    },
});

const pageProps = reactive({ props: { flash: {} } });

let formIndex = 0;
const forms = [loginForm, signupForm];

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    useForm: () => forms[formIndex++ % forms.length],
    usePage: () => pageProps,
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import AccessCard from "@/pages/Auth/AccessCard.vue";

const stubs = {
    CenteredLayout: { template: "<div><slot name='header' /><slot /></div>" },
};
const mountCard = () => {
    formIndex = 0;
    return mount(AccessCard, { props: { activeTab: "personal-link" }, global: { stubs } });
};

beforeEach(() => {
    formIndex = 0;
    loginForm.calls = [];
    signupForm.calls = [];
    signupForm.errors = {};
    signupForm.first_name = "";
    signupForm.last_name = "";
    signupForm.email = "";
    pageProps.props.flash = {};
});

describe("Auth/AccessCard — personal-link tab", () => {
    it("shows the three fields and the submit button", () => {
        const w = mountCard();
        expect(w.text()).toContain("First name");
        expect(w.text()).toContain("Last name");
        expect(w.find('input[inputmode="email"]').exists()).toBe(true);
        expect(w.findAll("button").some((b) => b.text() === "Send me the link")).toBe(true);
    });

    it("does not show the signin fields", () => {
        const w = mountCard();
        expect(w.find('input[type="password"]').exists()).toBe(false);
        expect(w.text()).not.toContain("Email me a login link");
    });

    it("posts to /signup on submit", async () => {
        const w = mountCard();
        await w.find("form").trigger("submit");
        expect(signupForm.calls).toContain("/signup");
    });

    it("replaces the form with the confirmation once flash.success is set", () => {
        pageProps.props.flash = { success: "If that address is valid, we sent your personal link." };
        const w = mountCard();
        expect(w.find("form").exists()).toBe(false);
        expect(w.text()).toContain("If that address is valid");
    });
});
