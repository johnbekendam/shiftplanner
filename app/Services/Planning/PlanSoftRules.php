<?php

namespace App\Services\Planning;

/**
 * Severity-weighted soft-rule data, precomputed once per run from a
 * {@see PlanProblem} for {@see PlanScorer}. Hard-rule data (which excludes
 * a candidate outright rather than costing a penalty) lives in
 * {@see PlanEligibility} instead — the two never overlap, since a rule's
 * `mode` picks one or the other.
 */
final class PlanSoftRules
{
    public bool $equalWorkload = false;

    /** @var array{severity: int}|null */
    public ?array $notPreferredShift = null;

    /** @var array<int, array{workcenter_id: int, competence_id: int, severity: int}> */
    public array $competenceRequired = [];

    /** @var array<int, array{workcenter_id: int, business_line_id: int, severity: int}> */
    public array $businessLinePreference = [];

    /** @var array{severity: int, value: int}|null */
    public ?array $maxShiftsPerDay = null;

    /** @var array{severity: int}|null */
    public ?array $maxHoursPerWeek = null;

    public function __construct(array $rules)
    {
        foreach ($rules as $rule) {
            if ($rule['type'] === 'equal_workload') {
                $this->equalWorkload = true;

                continue;
            }

            if ($rule['mode'] !== 'soft') {
                continue;
            }

            match ($rule['type']) {
                'not_preferred_shift' => $this->notPreferredShift = ['severity' => $rule['severity']],
                'competence_required' => $this->competenceRequired[] = [
                    'workcenter_id' => $rule['config']['workcenter_id'],
                    'competence_id' => $rule['config']['competence_id'],
                    'severity' => $rule['severity'],
                ],
                'business_line_preference' => $this->businessLinePreference[] = [
                    'workcenter_id' => $rule['config']['workcenter_id'],
                    'business_line_id' => $rule['config']['business_line_id'],
                    'severity' => $rule['severity'],
                ],
                'max_shifts_per_day' => $this->maxShiftsPerDay = ['severity' => $rule['severity'], 'value' => $rule['config']['value']],
                'max_hours_per_week' => $this->maxHoursPerWeek = ['severity' => $rule['severity']],
                default => null,
            };
        }
    }
}
