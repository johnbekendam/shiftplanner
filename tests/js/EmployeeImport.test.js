import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "import.title": "Import employees",
    "import.heading": "Import employees",
    "import.intro": "The first row is skipped. Two columns: name, then email.",
    "import.dropzone": "Drop a CSV here or click to browse",
    "import.busy": "Importing…",
    "import.result.summary": ":created created, :updated updated",
    "import.result.errors_heading": "Import failed — fix these rows and upload again:",
    "import.error.file": "Upload a .csv file (max 2 MB).",
};

const axiosPost = vi.fn();
vi.mock("axios", () => ({ default: { post: (...args) => axiosPost(...args) } }));

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeeImport from "@/pages/EmployeeImport.vue";
import { FileInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const csv = () => new File(["name,email\nJane,jane@x.com\n"], "employees.csv", { type: "text/csv" });

beforeEach(() => {
    axiosPost.mockReset();
});

describe("EmployeeImport", () => {
    it("renders a drop zone", () => {
        const w = mount(EmployeeImport, { global: { stubs } });

        expect(w.get('[data-testid="dropzone"]').text()).toContain("Drop a CSV here or click to browse");
    });

    it("POSTs the picked file to /import as multipart form data", async () => {
        axiosPost.mockResolvedValue({ data: { created: 1, updated: 0 } });
        const w = mount(EmployeeImport, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", csv());
        await flushPromises();

        expect(axiosPost).toHaveBeenCalledTimes(1);
        const [url, body] = axiosPost.mock.calls[0];
        expect(url).toBe("/import");
        expect(body).toBeInstanceOf(FormData);
        expect(body.get("file")).toBeInstanceOf(File);
    });

    it("shows a summary after a successful import", async () => {
        axiosPost.mockResolvedValue({ data: { created: 3, updated: 2 } });
        const w = mount(EmployeeImport, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", csv());
        await flushPromises();

        expect(w.get('[data-testid="import-summary"]').text()).toBe("3 created, 2 updated");
        expect(w.find('[data-testid="import-errors"]').exists()).toBe(false);
    });

    it("lists the row errors after a rejected import", async () => {
        axiosPost.mockRejectedValue({
            response: { status: 422, data: { errors: ["line 3: invalid email", "line 5: expected 2 columns"] } },
        });
        const w = mount(EmployeeImport, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", csv());
        await flushPromises();

        const errors = w.get('[data-testid="import-errors"]');
        expect(errors.text()).toContain("line 3: invalid email");
        expect(errors.text()).toContain("line 5: expected 2 columns");
        expect(w.find('[data-testid="import-summary"]').exists()).toBe(false);
    });

    it("drops a file dragged onto the zone", async () => {
        axiosPost.mockResolvedValue({ data: { created: 1, updated: 0 } });
        const w = mount(EmployeeImport, { global: { stubs } });

        await w.get('[data-testid="dropzone"]').trigger("drop", {
            dataTransfer: { files: [csv()] },
        });
        await flushPromises();

        expect(axiosPost).toHaveBeenCalledTimes(1);
    });
});
