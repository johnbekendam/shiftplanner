import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "auth.page_title": "Sign in",
    "auth.card_title": "Sign in to your account",
    "auth.field.email": "Email",
    "auth.field.password": "Password",
    "auth.field.code": "Sign-in code",
    "auth.action.signin": "Sign in",
    "auth.action.signing_in": "Signing in…",
    "auth.action.request_code": "Email me a code",
    "auth.action.verify_code": "Verify",
    "auth.code_sent": "If that email matches an account, a code is on the way.",
};

const form = reactive({
    email: "",
    password: "",
    code: "",
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
    form.code = "";
});

describe("Auth/Login", () => {
    it("shows email, password, Sign in, and Email me a code", () => {
        const w = mountLogin();
        expect(w.text()).toContain("Sign in to your account");
        expect(w.text()).toContain("Email me a code");
        const pwd = w.find('input[type="password"]');
        expect(pwd.exists()).toBe(true);
        expect(pwd.attributes("required")).toBeUndefined();
    });

    it("posts to /login on submit", async () => {
        const w = mountLogin();
        await w.findAll("form")[0].trigger("submit");
        expect(form.calls).toContain("/login");
    });

    it("requests a code, then reveals the code field and the notice", async () => {
        const w = mountLogin();
        expect(w.find('[data-testid="code-section"]').exists()).toBe(false);

        const codeBtn = w.findAll("button").find((b) => b.text() === "Email me a code");
        await codeBtn.trigger("click");

        expect(form.calls).toContain("/login/code");
        expect(w.find('[data-testid="code-section"]').exists()).toBe(true);
        expect(w.text()).toContain("If that email matches an account");
    });

    it("verifies the code against /login/code/verify", async () => {
        const w = mountLogin();
        await w.findAll("button").find((b) => b.text() === "Email me a code").trigger("click");
        await w.get('[data-testid="code-section"]').get("form").trigger("submit");

        expect(form.calls).toContain("/login/code/verify");
    });
});
