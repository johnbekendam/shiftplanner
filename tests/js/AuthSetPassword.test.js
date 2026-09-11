import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "auth.setpw.title": "Set your password",
    "auth.setpw.intro": "A password is optional. Set one below, or continue without one.",
    "auth.setpw.field.password": "Password",
    "auth.setpw.field.confirm": "Confirm password",
    "auth.setpw.submit": "Set password and sign in",
    "auth.setpw.skip": "Continue without password",
};

const form = reactive({
    password: "",
    password_confirmation: "",
    errors: {},
    processing: false,
    calls: [],
    post(url) {
        form.calls.push(url);
    },
});

const { router } = vi.hoisted(() => ({ router: { post: vi.fn((url, data, opts) => opts?.onFinish?.()) } }));

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    router,
    useForm: () => form,
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import SetPassword from "@/pages/Auth/SetPassword.vue";

const stubs = { CenteredLayout: { template: "<div><slot name='title' /><slot /></div>" } };
const mountPage = () => mount(SetPassword, { props: { token: "abc123" }, global: { stubs } });

beforeEach(() => {
    form.calls = [];
    form.errors = {};
    form.password = "";
    form.password_confirmation = "";
    router.post.mockClear();
});

describe("Auth/SetPassword", () => {
    it("shows the intro explaining a password is optional", () => {
        const w = mountPage();
        expect(w.text()).toContain("A password is optional");
    });

    it("submits password and confirmation to the token URL", async () => {
        const w = mountPage();
        await w.find("form").trigger("submit");

        expect(form.calls).toContain("/login/link/abc123");
    });

    it("shows Continue without password and posts to the skip route", async () => {
        const w = mountPage();
        const btn = w.findAll("button").find((b) => b.text() === "Continue without password");
        expect(btn).toBeTruthy();

        await btn.trigger("click");

        expect(router.post).toHaveBeenCalledWith("/login/link/abc123/skip", {}, expect.anything());
        expect(form.calls).toEqual([]);
    });
});
