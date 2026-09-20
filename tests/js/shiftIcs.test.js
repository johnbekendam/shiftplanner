import { describe, it, expect } from "vitest";
import { buildShiftIcs, shiftIcsFilename } from "@/utils/shiftIcs";

const assignment = {
    date: "2026-09-08",
    shift_name: "Early",
    start_time: "06:00:00",
    end_time: "14:30:00",
    workcenter_name: "Line 1",
    responsible: "Jane Doe",
};
const now = new Date("2026-09-20T09:15:30Z");

const lines = (ics) => ics.split("\r\n");

describe("buildShiftIcs", () => {
    it("wraps one event in a calendar with CRLF line endings", () => {
        const ics = buildShiftIcs(assignment, { now });

        expect(ics.startsWith("BEGIN:VCALENDAR\r\n")).toBe(true);
        expect(ics.endsWith("END:VCALENDAR\r\n")).toBe(true);
        expect(lines(ics)).toContain("VERSION:2.0");
        expect(lines(ics).filter((l) => l === "BEGIN:VEVENT")).toHaveLength(1);
    });

    it("uses floating local start and end times from the shift", () => {
        const l = lines(buildShiftIcs(assignment, { now }));

        expect(l).toContain("DTSTART:20260908T060000");
        expect(l).toContain("DTEND:20260908T143000");
    });

    it("accepts HH:MM times without seconds", () => {
        const l = lines(buildShiftIcs({ ...assignment, start_time: "06:00", end_time: "14:00" }, { now }));

        expect(l).toContain("DTSTART:20260908T060000");
        expect(l).toContain("DTEND:20260908T140000");
    });

    it("sets title, location and the contact in the description", () => {
        const l = lines(buildShiftIcs(assignment, { now }));

        expect(l).toContain("SUMMARY:Early – Line 1");
        expect(l).toContain("LOCATION:Line 1");
        expect(l).toContain("DESCRIPTION:Contact: Jane Doe");
    });

    it("uses the given label for the contact", () => {
        const l = lines(buildShiftIcs(assignment, { now, contactLabel: "Contactpersoon" }));

        expect(l).toContain("DESCRIPTION:Contactpersoon: Jane Doe");
    });

    it("leaves out the description when there is no contact", () => {
        const ics = buildShiftIcs({ ...assignment, responsible: null }, { now });

        expect(ics).not.toContain("DESCRIPTION");
    });

    it("stamps the event in UTC", () => {
        expect(lines(buildShiftIcs(assignment, { now }))).toContain("DTSTAMP:20260920T091530Z");
    });

    it("gives the same UID for the same shift and a different one otherwise", () => {
        const uid = (a) => lines(buildShiftIcs(a, { now })).find((l) => l.startsWith("UID:"));

        expect(uid(assignment)).toBe(uid({ ...assignment, responsible: "Other" }));
        expect(uid(assignment)).not.toBe(uid({ ...assignment, date: "2026-09-09" }));
        expect(uid(assignment)).not.toBe(uid({ ...assignment, shift_name: "Late" }));
        expect(uid(assignment)).not.toBe(uid({ ...assignment, workcenter_name: "Line 2" }));
        expect(uid(assignment)).toMatch(/^UID:[a-z0-9-]+@shiftplanner$/);
    });

    it("escapes commas, semicolons, backslashes and newlines in text", () => {
        const l = lines(buildShiftIcs({ ...assignment, workcenter_name: "A, B; C\\D\nE" }, { now }));

        expect(l).toContain("LOCATION:A\\, B\; C\\\\D\\nE");
    });

    it("folds lines longer than 75 characters", () => {
        const ics = buildShiftIcs({ ...assignment, workcenter_name: "x".repeat(200) }, { now });

        expect(lines(ics).every((l) => l.length <= 75)).toBe(true);
        expect(ics.replace(/\r\n /g, "")).toContain(`LOCATION:${"x".repeat(200)}`);
    });
});

describe("shiftIcsFilename", () => {
    it("names the file after the date as dd-mm-yyyy", () => {
        expect(shiftIcsFilename(assignment)).toBe("shift-08-09-2026.ics");
    });
});
