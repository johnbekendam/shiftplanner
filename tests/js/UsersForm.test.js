import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "users.form.create_title": "Add user",
    "users.form.edit_title": "Edit user",
    "users.form.view_title": "User",
    "users.field.name": "Name",
    "users.field.email": "Email",
    "users.field.role": "Role",
    "users.field.active": "Active",
    "users.field.business_line": "Business line",
    "users.field.business_line_none": "None",
    "users.role.admin": "Admin",
    "users.role.manager": "Manager",
    "users.action.save": "Save",
    "users.action.cancel": "Cancel",
    "users.action.back": "Back",
    "users.create_hint": "The user signs in with a one-time email code.",
};

const state = vi.hoisted(() => ({ user: { role: "admin" } }));

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
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
        post: vi.fn(),
        put: vi.fn(),
    }),
}));

vi.mock("@/composables/useI18n", () => ({ useI18n: () => (k) => en[k] ?? k }));

import Form from "@/pages/Users/Form.vue";
import { CheckboxInput } from "@/components/ui/Input";
import SelectInput from "@/components/ui/Input/Select.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

beforeEach(() => {
    state.user = { role: "admin" };
});

describe("Users/Form", () => {
    it("create: no active toggle, shows the code hint, posts to /users", async () => {
        const w = mount(Form, { props: { user: null }, global: { stubs } });

        expect(w.findComponent(CheckboxInput).exists()).toBe(false);
        expect(w.text()).toContain("one-time email code");

        await w.get("form").trigger("submit");
        expect(w.vm.form.post).toHaveBeenCalledWith("/users");
    });

    it("edit: has an active toggle and puts to /users/{id}", async () => {
        const w = mount(Form, {
            props: { user: { id: 7, name: "A", email: "a@b.c", role: "manager", is_active: true } },
            global: { stubs },
        });

        expect(w.findComponent(CheckboxInput).exists()).toBe(true);

        await w.get("form").trigger("submit");
        expect(w.vm.form.put).toHaveBeenCalledWith("/users/7");
    });

    it("edit as a manager: fields are disabled, no Save button, Back label", () => {
        state.user = { role: "manager" };
        const w = mount(Form, {
            props: { user: { id: 7, name: "A", email: "a@b.c", role: "manager", is_active: true } },
            global: { stubs },
        });

        expect(w.text()).toContain("User");
        expect(w.findAll("button").some((b) => b.text() === "Save")).toBe(false);
        expect(w.findAll("button").some((b) => b.text() === "Back")).toBe(true);

        w.findAll("input, select").forEach((el) => {
            expect(el.attributes("disabled")).toBeDefined();
        });
    });

    it("hides the business-line select when no lines are given", () => {
        const w = mount(Form, { props: { user: null }, global: { stubs } });
        expect(w.text()).not.toContain("Business line");
        expect(w.findAllComponents(SelectInput)).toHaveLength(1); // role only
    });

    it("offers a None option then one per business line, bound to form.business_line_id", async () => {
        const w = mount(Form, {
            props: {
                user: { id: 7, name: "A", email: "a@b.c", role: "manager", is_active: true, business_line_id: 5 },
                businessLines: [
                    { id: 5, abbreviation: "PMP" },
                    { id: 8, abbreviation: "VLV" },
                ],
            },
            global: { stubs },
        });

        const select = w.findAllComponents(SelectInput)[1];
        expect(select.props("options")).toEqual([
            { value: null, label: "None" },
            { value: 5, label: "PMP" },
            { value: 8, label: "VLV" },
        ]);
        expect(select.props("modelValue")).toBe(5);

        select.vm.$emit("update:modelValue", null);
        await w.vm.$nextTick();
        expect(w.vm.form.business_line_id).toBe(null);
    });

    it("edit as a manager viewing their own record: unlocks the business line select and saves it via the account endpoint", async () => {
        state.user = { role: "manager", id: 7 };
        const w = mount(Form, {
            props: {
                user: { id: 7, name: "A", email: "a@b.c", role: "manager", is_active: true, business_line_id: 5 },
                businessLines: [
                    { id: 5, abbreviation: "PMP" },
                    { id: 8, abbreviation: "VLV" },
                ],
            },
            global: { stubs },
        });

        const [name, email] = w.findAll("input");
        expect(name.attributes("disabled")).toBeDefined();
        expect(email.attributes("disabled")).toBeDefined();

        const businessLineSelect = w.findAllComponents(SelectInput)[1];
        expect(businessLineSelect.props("disabled")).toBe(false);
        expect(businessLineSelect.props("modelValue")).toBe(5);

        const saveButtons = w.findAll("button").filter((b) => b.text() === "Save");
        expect(saveButtons).toHaveLength(1);
        expect(saveButtons[0].attributes("disabled")).toBeDefined();

        businessLineSelect.vm.$emit("update:modelValue", 8);
        await w.vm.$nextTick();
        expect(saveButtons[0].attributes("disabled")).toBeUndefined();

        await saveButtons[0].trigger("click");
        expect(w.vm.selfBusinessLineForm.put).toHaveBeenCalledWith("/account/business-line", expect.anything());
        expect(w.vm.form.put).not.toHaveBeenCalled();
    });

    it("edit as a manager viewing someone else's record: the business line select stays disabled with no extra Save", () => {
        state.user = { role: "manager", id: 99 };
        const w = mount(Form, {
            props: {
                user: { id: 7, name: "A", email: "a@b.c", role: "manager", is_active: true, business_line_id: 5 },
                businessLines: [{ id: 5, abbreviation: "PMP" }],
            },
            global: { stubs },
        });

        const businessLineSelect = w.findAllComponents(SelectInput)[1];
        expect(businessLineSelect.props("disabled")).toBe(true);
        expect(w.findAll("button").some((b) => b.text() === "Save")).toBe(false);
    });

    it("create: defaults business_line_id to null and submits it", async () => {
        const w = mount(Form, {
            props: { user: null, businessLines: [{ id: 5, abbreviation: "PMP" }] },
            global: { stubs },
        });

        expect(w.vm.form.business_line_id).toBe(null);
        await w.get("form").trigger("submit");
        expect(w.vm.form.post).toHaveBeenCalledWith("/users");
    });
});
