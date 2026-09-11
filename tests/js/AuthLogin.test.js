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
    "auth.personal_link.intro": "Already have a personal page? Enter your email and we will send you the link again.",
    "auth.personal_link.field.email": "Email",
    "auth.personal_link.submit": "Send me the link",
    "auth.personal_link.sent": "If that email matches an employee, the link is on the way.",
    "auth.personal_link.new_employee": "New employee? Request access",
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

const personalLinkForm = reactive({
    email: "",
    errors: {},
    processing: false,
    calls: [],
    reset() {},
    clearErrors() {},
    post(url, opts) {
        personalLinkForm.calls.push(url);
        opts?.onSuccess?.();
        opts?.onFinish?.();
    },
});

let formIndex = 0;
const forms = [loginForm, personalLinkForm];

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a :href='href'><slot /></a>" },
    useForm: () => forms[formIndex++ % forms.length],
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
    return mount(AccessCard, { global: { stubs } });
};

beforeEach(() => {
    formIndex = 0;
    loginForm.calls = [];
    loginForm.errors = {};
    loginForm.email = "";
    loginForm.password = "";
    personalLinkForm.calls = [];
    personalLinkForm.errors = {};
    personalLinkForm.email = "";
});

describe("Auth/AccessCard — signin tab", () => {
    it("shows email, password, Sign in, and Email me a login link", () => {
        const w = mountCard();
        expect(w.text()).toContain("Email me a login link");
        const pwd = w.find('input[type="password"]');
        expect(pwd.exists()).toBe(true);
        expect(pwd.attributes("required")).toBeUndefined();
    });

    it("does not show the personal-link fields", () => {
        const w = mountCard();
        expect(w.text()).not.toContain("New employee?");
    });

    it("posts to /login on submit", async () => {
        const w = mountCard();
        await w.findAll("form")[0].trigger("submit");
        expect(loginForm.calls).toContain("/login");
    });

    it("requests a login link and shows the notice", async () => {
        const w = mountCard();
        expect(w.find('[data-testid="link-notice"]').exists()).toBe(false);

        const btn = w.findAll("button").find((b) => b.text() === "Email me a login link");
        await btn.trigger("click");

        expect(loginForm.calls).toContain("/login/link");
        expect(w.find('[data-testid="link-notice"]').exists()).toBe(true);
        expect(w.text()).toContain("If that email matches an account");
    });
});

describe("Auth/AccessCard — personal-link tab", () => {
    async function switchToPersonalLink(w) {
        const tabBtn = w.findAll("button").find((b) => b.text() === "Get my link");
        await tabBtn.trigger("click");
    }

    it("shows only an email field and the resend button, with the intro text", async () => {
        const w = mountCard();
        await switchToPersonalLink(w);

        expect(w.text()).toContain("Already have a personal page?");
        expect(w.find('input[type="password"]').exists()).toBe(false);
        expect(w.findAll("button").some((b) => b.text() === "Send me the link")).toBe(true);
    });

    it("links to /signup for a new employee", async () => {
        const w = mountCard();
        await switchToPersonalLink(w);

        const link = w.findAll("a").find((a) => a.text() === "New employee? Request access");
        expect(link).toBeTruthy();
        expect(link.attributes("href")).toBe("/signup");
    });

    it("posts only the email to /personal-link and shows the notice", async () => {
        const w = mountCard();
        await switchToPersonalLink(w);

        expect(w.find('[data-testid="personal-link-notice"]').exists()).toBe(false);
        await w.find("form").trigger("submit");

        expect(personalLinkForm.calls).toContain("/personal-link");
        expect(w.find('[data-testid="personal-link-notice"]').exists()).toBe(true);
        expect(w.text()).toContain("If that email matches an employee");
    });
});

describe("Auth/AccessCard — tab switching", () => {
    it("switches from signin to personal-link and back", async () => {
        const w = mountCard();
        expect(w.text()).toContain("Email me a login link");
        expect(w.text()).not.toContain("New employee?");

        const getLinkTab = w.findAll("button").find((b) => b.text() === "Get my link");
        await getLinkTab.trigger("click");

        expect(w.text()).toContain("New employee?");
        expect(w.text()).not.toContain("Email me a login link");

        const signInTab = w.findAll("button").find((b) => b.text() === "Sign in");
        await signInTab.trigger("click");

        expect(w.text()).toContain("Email me a login link");
        expect(w.text()).not.toContain("New employee?");
    });
});
