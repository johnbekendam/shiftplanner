<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\Workcenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PlanningRuleController extends Controller
{
    public function index()
    {
        return Inertia::render('PlanningRules', [
            'planningRules' => PlanningRule::all()->map->toPayload()->all(),
            'workcenters' => Workcenter::query()
                ->whereNull('archived_at')
                ->get()
                ->map(fn (Workcenter $w) => ['id' => $w->id, 'name' => $w->name])
                ->all(),
            'competences' => Competence::all()
                ->map(fn (Competence $c) => ['id' => $c->id, 'name' => $c->name])
                ->all(),
            'businessLines' => BusinessLine::all()
                ->map(fn (BusinessLine $b) => ['id' => $b->id, 'abbreviation' => $b->abbreviation])
                ->all(),
            'shifts' => Shift::query()
                ->orderBy('start_time')
                ->get()
                ->map(fn (Shift $s) => ['id' => $s->id, 'name' => $s->name])
                ->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $this->guardAgainstDuplicate($data);

        PlanningRule::create($this->toAttributes($data));

        return back()->with('success', __('planning_rules.flash.saved'));
    }

    public function update(Request $request, PlanningRule $planningRule)
    {
        $merged = $request->all();
        $merged['type'] = $planningRule->type;

        // A rule's identity (type, and for a scoped type, what it targets)
        // is fixed once created — remove and re-add to change it, same rule
        // as workcenter-shift-assignments. Client input for those fields is
        // ignored in favor of what is already stored.
        foreach ($this->identityFields($planningRule->type) as $field) {
            $merged[$field] = $planningRule->config[$field] ?? null;
        }

        $data = Validator::make($merged, $this->rules($planningRule->type))->validate();

        $this->guardAgainstDuplicate($data, ignoreId: $planningRule->id);

        $planningRule->update($this->toAttributes($data));

        return back()->with('success', __('planning_rules.flash.saved'));
    }

    public function destroy(PlanningRule $planningRule)
    {
        $planningRule->delete();

        return back()->with('success', __('planning_rules.flash.saved'));
    }

    private function rules(?string $type = null): array
    {
        $type ??= request()->input('type');

        return [
            'type' => ['required', Rule::in(PlanningRule::TYPES)],
            'mode' => $type === 'equal_workload' || $type === 'alternating_shift_pair'
                ? ['nullable', Rule::in(['hard', 'soft'])]
                : ['required', Rule::in(['hard', 'soft'])],
            'severity' => match ($type) {
                'equal_workload' => ['nullable', 'prohibited'],
                // Always hard; a client-sent severity is accepted and ignored.
                'alternating_shift_pair' => ['nullable'],
                default => ['required_if:mode,soft', 'prohibited_unless:mode,soft', 'nullable', 'integer', 'between:1,10'],
            },
            'value' => ['required_if:type,max_shifts_per_day', 'integer', 'min:1'],
            'workcenter_id' => [
                'required_if:type,competence_required,business_line_preference',
                'integer', 'exists:workcenters,id',
            ],
            'competence_id' => ['required_if:type,competence_required', 'integer', 'exists:competences,id'],
            'business_line_id' => ['required_if:type,business_line_preference', 'integer', 'exists:business_lines,id'],
            'first_shift_id' => ['required_if:type,alternating_shift_pair', 'integer', 'exists:shifts,id'],
            'second_shift_id' => ['required_if:type,alternating_shift_pair', 'integer', 'different:first_shift_id', 'exists:shifts,id'],
        ];
    }

    private function guardAgainstDuplicate(array $data, ?int $ignoreId = null): void
    {
        $query = PlanningRule::query()->where('type', $data['type']);
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if (in_array($data['type'], PlanningRule::SINGLETON_TYPES, true)) {
            if ($query->exists()) {
                throw ValidationException::withMessages(['type' => __('planning_rules.error.duplicate_type')]);
            }

            return;
        }

        $existing = $query->get();

        if ($data['type'] === 'competence_required') {
            $duplicate = $existing->contains(fn (PlanningRule $rule) => $rule->config['workcenter_id'] === $data['workcenter_id']
                && $rule->config['competence_id'] === $data['competence_id']);

            if ($duplicate) {
                throw ValidationException::withMessages(['competence_id' => __('planning_rules.error.duplicate_competence_requirement')]);
            }
        }

        if ($data['type'] === 'business_line_preference') {
            $duplicate = $existing->contains(fn (PlanningRule $rule) => $rule->config['workcenter_id'] === $data['workcenter_id']);

            if ($duplicate) {
                throw ValidationException::withMessages(['workcenter_id' => __('planning_rules.error.duplicate_business_line_preference')]);
            }
        }

        if ($data['type'] === 'alternating_shift_pair') {
            $shiftIds = [(int) $data['first_shift_id'], (int) $data['second_shift_id']];
            $duplicate = $existing->contains(function (PlanningRule $rule) use ($shiftIds) {
                $existingIds = [
                    (int) $rule->config['first_shift_id'],
                    (int) $rule->config['second_shift_id'],
                ];

                return count(array_intersect($shiftIds, $existingIds)) > 0;
            });

            if ($duplicate) {
                throw ValidationException::withMessages(['first_shift_id' => __('planning_rules.error.duplicate_shift_pair')]);
            }
        }
    }

    private function identityFields(string $type): array
    {
        return match ($type) {
            'competence_required' => ['workcenter_id', 'competence_id'],
            'business_line_preference' => ['workcenter_id'],
            'alternating_shift_pair' => ['first_shift_id', 'second_shift_id'],
            default => [],
        };
    }

    private function toAttributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'mode' => match ($data['type']) {
                'equal_workload' => null,
                'alternating_shift_pair' => 'hard',
                default => $data['mode'],
            },
            'severity' => in_array($data['type'], ['equal_workload', 'alternating_shift_pair'], true) || $data['mode'] !== 'soft'
                ? null
                : $data['severity'],
            'config' => match ($data['type']) {
                'max_shifts_per_day' => ['value' => $data['value']],
                'competence_required' => [
                    'workcenter_id' => $data['workcenter_id'],
                    'competence_id' => $data['competence_id'],
                ],
                'business_line_preference' => [
                    'workcenter_id' => $data['workcenter_id'],
                    'business_line_id' => $data['business_line_id'],
                ],
                'alternating_shift_pair' => [
                    'first_shift_id' => $data['first_shift_id'],
                    'second_shift_id' => $data['second_shift_id'],
                ],
                default => [],
            },
        ];
    }
}
