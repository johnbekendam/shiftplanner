import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "auth.signin_link.title": "Sign in",
    "auth.signin_link.submit": "Sign in",
};

const form = reactive({
    errors: {},
    processing: false,
    calls: [],
    post(url) {
        form.calls.push(url);
    },
});

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    useForm: () => form,
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import SignInLink from "@/pages/Auth/SignInLink.vue";

const stubs = { CenteredLayout: { template: "<div><slot name='title' /><slot /></div>" } };

beforeEach(() => {
    form.calls = [];
});

describe("Auth/SignInLink", () => {
    it("posts to the token URL to sign in", async () => {
        const w = mount(SignInLink, { props: { token: "abc123" }, global: { stubs } });
        await w.find("form").trigger("submit");

        expect(form.calls).toContain("/login/link/abc123");
    });
});
