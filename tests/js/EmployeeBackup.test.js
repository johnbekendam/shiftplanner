import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "backup.title": "Application backup",
    "backup.heading": "Application backup",
    "backup.export": "Export backup",
    "backup.import": "Import backup",
    "backup.dropzone": "Drop a backup file here or click to browse",
    "backup.busy": "Importing…",
    "backup.result.summary": ":created records created, :updated records updated",
    "backup.result.imported": ":imported records restored.",
    "backup.result.errors_heading": "Nothing was imported. Fix the backup and upload it again:",
    "backup.error.file": "Upload a ShiftPlanner backup file no larger than 50 MB.",
};

const axiosPost = vi.fn();
vi.mock("axios", () => ({ default: { post: (...args) => axiosPost(...args) } }));

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeeBackup from "@/pages/EmployeeBackup.vue";
import ConfirmDialog from "@/components/ui/ConfirmDialog.vue";
import { FileInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };
const archive = () => new File(['{"version":1,"employees":[]}'], "employees-backup.json", { type: "application/json" });

beforeEach(() => axiosPost.mockReset());

describe("EmployeeBackup", () => {
    it("offers a JSON archive download and upload", () => {
        const w = mount(EmployeeBackup, { global: { stubs } });

        expect(w.get('[data-testid="backup-export"]').element.closest("form").getAttribute("action")).toBe("/employee-backup/export");
        expect(w.get('[data-testid="backup-dropzone"]').text()).toContain("Drop a backup file here");
    });

    it("uploads the selected archive to the backup import endpoint", async () => {
        axiosPost.mockResolvedValue({ data: { created: 1, updated: 0 } });
        const w = mount(EmployeeBackup, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();
        expect(w.findComponent(ConfirmDialog).props("open")).toBe(true);
        expect(w.findComponent(ConfirmDialog).props("variant")).toBe("danger");
        expect(axiosPost).not.toHaveBeenCalled();

        w.findComponent(ConfirmDialog).vm.$emit("confirm");
        await flushPromises();

        const [url, body] = axiosPost.mock.calls[0];
        expect(url).toBe("/employee-backup/import");
        expect(body.get("file")).toBeInstanceOf(File);
    });

    it("does not upload when the import is cancelled", async () => {
        const w = mount(EmployeeBackup, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();
        w.findComponent(ConfirmDialog).vm.$emit("cancel");
        await flushPromises();

        expect(axiosPost).not.toHaveBeenCalled();
        expect(w.findComponent(ConfirmDialog).props("open")).toBe(false);
    });

    it("shows the import summary or archive errors", async () => {
        axiosPost.mockResolvedValueOnce({ data: { created: 3, updated: 2 } });
        const w = mount(EmployeeBackup, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();
        w.findComponent(ConfirmDialog).vm.$emit("confirm");
        await flushPromises();
        expect(w.get('[data-testid="backup-summary"]').text()).toBe("3 records created, 2 records updated");

        axiosPost.mockRejectedValueOnce({ response: { data: { errors: ["Record 2 is invalid."] } } });
        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();
        w.findComponent(ConfirmDialog).vm.$emit("confirm");
        await flushPromises();
        expect(w.get('[data-testid="backup-errors"]').text()).toContain("Record 2 is invalid.");
    });

    it("shows the complete archive restore summary", async () => {
        axiosPost.mockResolvedValue({ data: { version: 2, imported: 4 } });
        const w = mount(EmployeeBackup, { global: { stubs } });

        w.findComponent(FileInput).vm.$emit("change", archive());
        await flushPromises();
        w.findComponent(ConfirmDialog).vm.$emit("confirm");
        await flushPromises();

        expect(w.get('[data-testid="backup-summary"]').text()).toBe("4 records restored.");
    });
});