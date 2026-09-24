<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityQuestion;
use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Services\EmployeeAuditLogger;
use App\Services\EmployeePersonalLinkService;
use App\Services\PlannedShifts;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The employee personal page, reached by an opaque preview token.
 *
 * PROTOTYPE ONLY — no authentication. See EmployeePersonalLinkService and
 * roadmap phase 2. A missing token on `show` (a stale or reused link,
 * e.g. after withdrawing) redirects to /signup with an error banner
 * instead of a 404, so the employee can immediately request a new one.
 * A missing token on every write action still 404s — those are only
 * ever reached from within an already-loaded page, never by following
 * a link, so there's nothing to redirect to.
 */
class PersonalPageController extends Controller
{
    public function __construct(
        private EmployeePersonalLinkService $links,
        private PlannedShifts $plannedShifts,
        private EmployeeAuditLogger $audit,
    ) {}

    public function show(string $token)
    {
        $employee = $this->links->resolve($token);

        if (! $employee) {
            return redirect('/signup')->with('error', __('personal.link_invalid'));
        }

        $responsible = $employee->businessLine?->responsibleUser;

        $visibleShifts = $employee->effectiveShifts();

        return Inertia::render('Personal/Show', [
            'token' => $token,
            'editable' => $employee->archived_at === null && PlanningSettings::current()->allow_employee_changes,
            'employee' => [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'weekly_hours' => $employee->weekly_hours,
                'business_line_id' => $employee->business_line_id,
            ],
            'weeklyHoursMinimum' => $employee->effectiveWeeklyHoursMinimum(),
            'businessLines' => BusinessLine::all()->map->toPayload()->all(),
            'holidays' => $employee->holidays->map->toPayload()->all(),
            'shifts' => $visibleShifts->map->toPayload()->values()->all(),
            'shiftNoteHtml' => PlanningSettings::current()->shiftNoteHtml($employee->first_name),
            'scheduleNoteHtml' => PlanningSettings::current()->scheduleNoteHtml($employee->first_name),
            'availability' => $employee->recurringAvailabilities
                ->whereIn('shift_id', $visibleShifts->pluck('id'))
                ->map->toPayload()->values()->all(),
            'competences' => Competence::all()->map->toPayload()->all(),
            'competenceIds' => $employee->competences->pluck('id')->all(),
            'questions' => AvailabilityQuestion::all()->map->toPayload()->all(),
            'questionAnswers' => $employee->availabilityQuestions->pluck('id')->all(),
            'plannedShifts' => $this->plannedShifts->forEmployee($employee, publishedOnly: true),
            // Who to contact instead of withdrawing; null when nobody active is set.
            'businessLineResponsible' => $responsible?->is_active
                ? ['name' => $responsible->name]
                : null,
        ]);
    }

    public function update(Request $request, string $token)
    {
        $employee = $this->resolveOrFail($token);

        $data = $request->validate([
            'weekly_hours' => ['required', 'integer', 'min:0', 'max:48'],
            'business_line_id' => ['nullable', 'integer', 'exists:business_lines,id'],
        ]);

        $before = $employee->only(array_keys($data));
        $employee->fill($data);
        $changed = array_keys($employee->getDirty());
        $employee->update($data);

        if ($changed !== []) {
            $this->audit->record(
                $employee,
                'updated',
                'employee',
                $employee->id,
                array_intersect_key($before, array_flip($changed)),
                $employee->only($changed),
                'employee_personal_link',
                actorType: 'employee',
                actorSnapshot: ['id' => $employee->id, 'name' => $employee->name, 'email' => $employee->email],
            );
        }

        return redirect("/personal/{$token}")->with('success', __('personal.flash.saved'));
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
