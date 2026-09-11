import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "auth.page_title": "Sign in",
    "auth.card_title": "Sign in to your account",
    "auth.field.email": "Email",
    "auth.field.password": "Password",
    "auth.action.signin": "Sign in",
    "auth.action.signing_in": "Signing in…",
    "auth.action.request_link": "Email me a login link",
    "auth.link_sent": "If that email matches an account, a link is on the way.",
    "signup.login_link": "Request your personal link",
};

const form = reactive({
    email: "",
    password: "",
    errors: {},
    processing: false,
    calls: [],
    reset() {},
    post(url, opts) {
        form.calls.push(url);
        opts?.onSuccess?.();
        opts?.onFinish?.();
    },
});

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
    useForm: () => form,
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import Login from "@/pages/Auth/Login.vue";

const stubs = { CenteredLayout: { template: "<div><slot name='title' /><slot /></div>" } };
const mountLogin = () => mount(Login, { global: { stubs } });

beforeEach(() => {
    form.calls = [];
    form.errors = {};
    form.email = "";
    form.password = "";
});

describe("Auth/Login", () => {
    it("shows email, password, Sign in, and Email me a login link — no code field", () => {
        const w = mountLogin();
        expect(w.text()).toContain("Sign in to your account");
        expect(w.text()).toContain("Email me a login link");
        const pwd = w.find('input[type="password"]');
        expect(pwd.exists()).toBe(true);
        expect(pwd.attributes("required")).toBeUndefined();
        expect(w.find('[data-testid="code-section"]').exists()).toBe(false);
    });

    it("links to the self-signup page", () => {
        const w = mountLogin();
        const link = w.findAll("a").find((a) => a.text() === "Request your personal link");
        expect(link).toBeTruthy();
        expect(link.attributes("href")).toBe("/signup");
    });

    it("posts to /login on submit", async () => {
        const w = mountLogin();
        await w.findAll("form")[0].trigger("submit");
        expect(form.calls).toContain("/login");
    });

    it("requests a login link and shows the notice", async () => {
        const w = mountLogin();
        expect(w.find('[data-testid="link-notice"]').exists()).toBe(false);

        const btn = w.findAll("button").find((b) => b.text() === "Email me a login link");
        await btn.trigger("click");

        expect(form.calls).toContain("/login/link");
        expect(w.find('[data-testid="link-notice"]').exists()).toBe(true);
        expect(w.text()).toContain("If that email matches an account");
    });
});
