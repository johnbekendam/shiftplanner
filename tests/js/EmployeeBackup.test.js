import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "backup.title": "Employee backup",
    "backup.heading": "Employee backup",
    "backup.export": "Export employees",
    "backup.import": "Import employees",
    "backup.dropzone": "Drop an employee backup here or click to browse",
    "backup.busy": "Importing…",
    "backup.result.summary": ":created created, :updated updated",
    "backup.result.errors_heading": "Nothing was imported. Fix the archive and upload it again:",
    "backup.error.file": "Upload an employee backup JSON file no larger than 2 MB.",
};

const axiosPost = vi.fn();
vi.mock("axios", () => ({ default: { post: (...args) => axiosPost(...args) } }));

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeeBackup from "@/pages/EmployeeBackup.vue";
import { FileInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };
const archive = () => new File(['{"version":1,"employees":[]}'], "employees-backup.json", { type: "application/json" });

beforeEach(() => axiosPost.mockReset());

describe("EmployeeBackup", () => {
    it("offers a JSON archive download and upload", () => {
        const w = mount(EmployeeBackup, { global: { stubs } });

        expect(w.get('[data-testid="backup-export"]').element.closest("form").getAttribute("action")).toBe("/employee-backup/export");
        expect(w.get('[data-testid="backup-dropzone"]').text()).toContain("Drop an employee backup here");
    });

    it("uploads the selected archive to the backup import endpoint", async () => {
        axiosPost.mockResolvedValue({ data: { created: 1, updated: 0 } });
        const w = mount(EmployeeBackup, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();

        const [url, body] = axiosPost.mock.calls[0];
        expect(url).toBe("/employee-backup/import");
        expect(body.get("file")).toBeInstanceOf(File);
    });

    it("shows the import summary or archive errors", async () => {
        axiosPost.mockResolvedValueOnce({ data: { created: 3, updated: 2 } });
        const w = mount(EmployeeBackup, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();
        expect(w.get('[data-testid="backup-summary"]').text()).toBe("3 created, 2 updated");

        axiosPost.mockRejectedValueOnce({ response: { data: { errors: ["Record 2 is invalid."] } } });
        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();
        expect(w.get('[data-testid="backup-errors"]').text()).toContain("Record 2 is invalid.");
    });
});