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
    "auth.action.signing_in": "Signing in…",
    "auth.action.request_link": "Email me a login link",
    "auth.link_sent": "If that email matches an account, a link is on the way.",
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
const mountCard = (activeTab = "signin") => {
    formIndex = 0;
    return mount(AccessCard, { props: { activeTab }, global: { stubs } });
};

beforeEach(() => {
    formIndex = 0;
    loginForm.calls = [];
    loginForm.errors = {};
    loginForm.email = "";
    loginForm.password = "";
    signupForm.calls = [];
    signupForm.errors = {};
    signupForm.first_name = "";
    signupForm.last_name = "";
    signupForm.email = "";
    pageProps.props.flash = {};
});

describe("Auth/AccessCard — signin tab", () => {
    it("shows email, password, Sign in, and Email me a login link", () => {
        const w = mountCard("signin");
        expect(w.text()).toContain("Email me a login link");
        const pwd = w.find('input[type="password"]');
        expect(pwd.exists()).toBe(true);
        expect(pwd.attributes("required")).toBeUndefined();
    });

    it("does not show the personal-link fields", () => {
        const w = mountCard("signin");
        expect(w.text()).not.toContain("First name");
    });

    it("posts to /login on submit", async () => {
        const w = mountCard("signin");
        await w.findAll("form")[0].trigger("submit");
        expect(loginForm.calls).toContain("/login");
    });

    it("requests a login link and shows the notice", async () => {
        const w = mountCard("signin");
        expect(w.find('[data-testid="link-notice"]').exists()).toBe(false);

        const btn = w.findAll("button").find((b) => b.text() === "Email me a login link");
        await btn.trigger("click");

        expect(loginForm.calls).toContain("/login/link");
        expect(w.find('[data-testid="link-notice"]').exists()).toBe(true);
        expect(w.text()).toContain("If that email matches an account");
    });
});

describe("Auth/AccessCard — tab switching", () => {
    it("switches from signin to personal-link and back", async () => {
        const w = mountCard("signin");
        expect(w.text()).toContain("Email me a login link");
        expect(w.text()).not.toContain("First name");

        const getLinkTab = w.findAll("button").find((b) => b.text() === "Get my link");
        await getLinkTab.trigger("click");

        expect(w.text()).toContain("First name");
        expect(w.text()).not.toContain("Email me a login link");

        const signInTab = w.findAll("button").find((b) => b.text() === "Sign in");
        await signInTab.trigger("click");

        expect(w.text()).toContain("Email me a login link");
        expect(w.text()).not.toContain("First name");
    });
});
