import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "users.form.create_title": "Add user",
    "users.form.edit_title": "Edit user",
    "users.field.name": "Name",
    "users.field.email": "Email",
    "users.field.role": "Role",
    "users.field.active": "Active",
    "users.role.admin": "Admin",
    "users.role.manager": "Manager",
    "users.action.save": "Save",
    "users.action.cancel": "Cancel",
    "users.create_hint": "The user signs in with a one-time email code.",
};

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    useForm: (initial) => reactive({ ...initial, errors: {}, processing: false, post: vi.fn(), put: vi.fn() }),
}));

vi.mock("@/composables/useI18n", () => ({ useI18n: () => (k) => en[k] ?? k }));

import Form from "@/pages/Users/Form.vue";
import { CheckboxInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

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
});
