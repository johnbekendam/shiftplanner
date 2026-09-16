import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "account.title": "Account",
    "account.email.label": "Email address",
    "account.password.current": "Current password",
    "account.password.new": "New password",
    "account.password.confirm": "Confirm new password",
    "account.password.save": "Save password",
    "account.password.none_hint": "You sign in with a one-time email code.",
    "account.employee.heading": "Employee",
    "account.employee.hint": "Add yourself as an employee.",
    "account.employee.add": "Add me as an employee",
    "account.business_line.heading": "Business line",
    "account.business_line.label": "Business line",
    "account.business_line.none": "None",
    "account.business_line.save": "Save business line",
};

const state = vi.hoisted(() => ({
    user: { role: "manager", employee_id: null, email: "mel@example.com", business_line_id: null },
}));
const { router } = vi.hoisted(() => ({ router: { post: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, auth: { user: state.user } } }),
    useForm: (initial) => reactive({
        ...initial,
        errors: {},
        processing: false,
        _defaults: { ...initial },
        get isDirty() {
            return Object.keys(initial).some((k) => this[k] !== this._defaults[k]);
        },
        defaults() {
            this._defaults = Object.fromEntries(Object.keys(initial).map((k) => [k, this[k]]));
        },
        put: vi.fn(),
        reset() {},
    }),
}));

import Show from "@/pages/Account/Show.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };
const mountShow = (props = {}) => mount(Show, { props, global: { stubs } });

beforeEach(() => {
    state.user = { role: "manager", employee_id: null, email: "mel@example.com", business_line_id: null };
    router.post.mockReset();
});

describe("Account/Show", () => {
    it("shows the registered email address, disabled", () => {
        const w = mountShow();
        const emailField = w.find('input[inputmode="email"]');

        expect(emailField.exists()).toBe(true);
        expect(emailField.element.value).toBe("mel@example.com");
        expect(emailField.attributes("disabled")).toBeDefined();
    });

    it("shows the current-password field only when the account has a password", () => {
        expect(mountShow({ hasPassword: false }).text()).not.toContain("Current password");
        expect(mountShow({ hasPassword: true }).text()).toContain("Current password");
    });

    it("puts the password form to /account/password", async () => {
        const w = mountShow({ hasPassword: true });
        await w.get("form").trigger("submit");
        expect(w.vm.passwordForm.put).toHaveBeenCalledWith("/account/password", expect.anything());
    });

    it("offers 'Add me as an employee' to an unlinked manager, right-aligned", async () => {
        const w = mountShow();
        const section = w.find('[data-testid="link-employee"]');
        expect(section.exists()).toBe(true);

        const button = section.get("button");
        expect(button.element.parentElement.className).toContain("justify-end");

        await button.trigger("click");
        expect(router.post).toHaveBeenCalledWith("/account/employee");
    });

    it("hides the employee section for an admin", () => {
        state.user = { role: "admin", employee_id: null };
        expect(mountShow().find('[data-testid="link-employee"]').exists()).toBe(false);
    });

    it("hides the employee section for a manager already linked", () => {
        state.user = { role: "manager", employee_id: 4 };
        expect(mountShow().find('[data-testid="link-employee"]').exists()).toBe(false);
    });

    it("offers a None option then one per business line, bound to the current user's value", () => {
        state.user = { role: "manager", employee_id: null, business_line_id: 5 };
        const w = mountShow({
            businessLines: [
                { id: 5, abbreviation: "PMP" },
                { id: 8, abbreviation: "VLV" },
            ],
        });

        const select = w.getComponent(SelectInput);
        expect(select.props("options")).toEqual([
            { value: null, label: "None" },
            { value: 5, label: "PMP" },
            { value: 8, label: "VLV" },
        ]);
        expect(select.props("modelValue")).toBe(5);
    });

    it("shows the business line picker for an admin too", () => {
        state.user = { role: "admin", employee_id: null, business_line_id: null };
        const w = mountShow({ businessLines: [{ id: 5, abbreviation: "PMP" }] });
        expect(w.findComponent(SelectInput).exists()).toBe(true);
    });

    it("puts the selected business line to /account/business-line", async () => {
        const w = mountShow({ businessLines: [{ id: 5, abbreviation: "PMP" }] });

        w.vm.businessLineForm.business_line_id = 5;
        await w.get('[data-testid="business-line-form"]').trigger("submit");

        expect(w.vm.businessLineForm.put).toHaveBeenCalledWith("/account/business-line", expect.anything());
    });

    it("disables the business line Save button until the value changes", async () => {
        const w = mountShow({ businessLines: [{ id: 5, abbreviation: "PMP" }] });
        const saveButton = w.get('[data-testid="business-line-form"]').get('button[type="submit"]');

        expect(saveButton.attributes("disabled")).toBeDefined();

        w.vm.businessLineForm.business_line_id = 5;
        await w.vm.$nextTick();

        expect(saveButton.attributes("disabled")).toBeUndefined();
    });
});
