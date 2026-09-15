<?php

namespace App\Http\Controllers;

use App\Models\PlanningSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanningRuleController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'max_hours_per_week_mode' => ['required', Rule::in(['hard', 'soft'])],
            'max_hours_per_week_severity' => $this->severityRules('max_hours_per_week_mode'),
            'max_shifts_per_day' => ['required', 'integer', 'min:1'],
            'max_shifts_per_day_mode' => ['required', Rule::in(['hard', 'soft'])],
            'max_shifts_per_day_severity' => $this->severityRules('max_shifts_per_day_mode'),
            'not_preferred_shift_mode' => ['required', Rule::in(['hard', 'soft'])],
            'not_preferred_shift_severity' => $this->severityRules('not_preferred_shift_mode'),
        ]);

        PlanningSettings::current()->update($data);

        return back()->with('success', __('planning_rules.flash.saved'));
    }

    /** Required and 1-10 when the paired mode field is "soft", must be absent otherwise. */
    private function severityRules(string $modeField): array
    {
        return [
            "required_if:{$modeField},soft",
            "prohibited_unless:{$modeField},soft",
            'nullable',
            'integer',
            'between:1,10',
        ];
    }
}
