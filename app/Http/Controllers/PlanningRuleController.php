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
            'shifts' => Shift::all()
                ->map(fn (Shift $s) => ['id' => $s->id, 'name' => $s->name])
                ->all(),
            'competences' => Competence::all()
                ->map(fn (Competence $c) => ['id' => $c->id, 'name' => $c->name])
                ->all(),
            'businessLines' => BusinessLine::all()
                ->map(fn (BusinessLine $b) => ['id' => $b->id, 'abbreviation' => $b->abbreviation])
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

        $data = Validator::make($merged, $this->rules())->validate();

        $this->guardAgainstDuplicate($data, ignoreId: $planningRule->id);

        $planningRule->update($this->toAttributes($data));

        return back()->with('success', __('planning_rules.flash.saved'));
    }

    public function destroy(PlanningRule $planningRule)
    {
        $planningRule->delete();

        return back()->with('success', __('planning_rules.flash.saved'));
    }

    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(PlanningRule::TYPES)],
            'mode' => ['required', Rule::in(['hard', 'soft'])],
            'severity' => [
                'required_if:mode,soft',
                'prohibited_unless:mode,soft',
                'nullable', 'integer', 'between:1,10',
            ],
            'value' => ['required_if:type,max_shifts_per_day', 'integer', 'min:1'],
            'workcenter_id' => [
                'required_if:type,competence_required,business_line_preference',
                'integer', 'exists:workcenters,id',
            ],
            'shift_id' => ['required_if:type,competence_required', 'integer', 'exists:shifts,id'],
            'competence_id' => ['required_if:type,competence_required', 'integer', 'exists:competences,id'],
            'business_line_ids' => ['required_if:type,business_line_preference', 'array', 'min:1'],
            'business_line_ids.*' => ['integer', 'exists:business_lines,id'],
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
                && $rule->config['shift_id'] === $data['shift_id']
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
    }

    private function identityFields(string $type): array
    {
        return match ($type) {
            'competence_required' => ['workcenter_id', 'shift_id', 'competence_id'],
            'business_line_preference' => ['workcenter_id'],
            default => [],
        };
    }

    private function toAttributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'mode' => $data['mode'],
            'severity' => $data['mode'] === 'soft' ? $data['severity'] : null,
            'config' => match ($data['type']) {
                'max_shifts_per_day' => ['value' => $data['value']],
                'competence_required' => [
                    'workcenter_id' => $data['workcenter_id'],
                    'shift_id' => $data['shift_id'],
                    'competence_id' => $data['competence_id'],
                ],
                'business_line_preference' => [
                    'workcenter_id' => $data['workcenter_id'],
                    'business_line_ids' => array_values($data['business_line_ids']),
                ],
                default => [],
            },
        ];
    }
}
