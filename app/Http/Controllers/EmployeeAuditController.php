<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeAuditController extends Controller
{
    public function index(Request $request): Response
    {
        $employee = trim((string) $request->input('employee', ''));
        $actor = trim((string) $request->input('actor', ''));
        $actions = EmployeeAuditEvent::query()->distinct()->orderBy('action')->pluck('action')->all();
        $sources = EmployeeAuditEvent::query()->distinct()->orderBy('source')->pluck('source')->all();
        $action = in_array($request->input('action'), $actions, true) ? $request->input('action') : null;
        $source = in_array($request->input('source'), $sources, true) ? $request->input('source') : null;
        $from = $this->dateFilter($request->input('from'));
        $to = $this->dateFilter($request->input('to'));

        $events = EmployeeAuditEvent::query()
            ->with('employee')
            ->when($employee !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('employee_name', 'like', "%{$employee}%")
                ->orWhere('employee_email', 'like', "%{$employee}%")
                ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery
                    ->where(fn ($legacySearch) => $legacySearch
                        ->where('first_name', 'like', "%{$employee}%")
                        ->orWhere('last_name', 'like', "%{$employee}%")
                        ->orWhere('email', 'like', "%{$employee}%")))))
            ->when($actor !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('actor_name', 'like', "%{$actor}%")
                ->orWhere('actor_email', 'like', "%{$actor}%")))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($source, fn ($query) => $query->where('source', $source))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (EmployeeAuditEvent $event) => [
                ...$event->only([
                    'id',
                    'employee_id',
                    'action',
                    'subject_type',
                    'subject_id',
                    'source',
                    'actor_type',
                    'actor_id',
                    'actor_name',
                    'actor_email',
                    'actor_role',
                    'old_values',
                    'new_values',
                ]),
                'employee_name' => $this->employeeName($event),
                'created_at' => $event->created_at->toIso8601String(),
            ]);

        return Inertia::render('EmployeeAudit/Index', [
            'events' => $events,
            'filters' => compact('employee', 'actor', 'action', 'source', 'from', 'to'),
            'actions' => $actions,
            'sources' => $sources,
        ]);
    }

    private function dateFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return Validator::make(['date' => $value], ['date' => ['date_format:Y-m-d']])->passes()
            ? $value
            : null;
    }

    private function employeeName(EmployeeAuditEvent $event): string
    {
        if ($event->employee_name !== null) {
            return $event->employee_name;
        }

        if ($event->employee !== null) {
            return $event->employee->name;
        }

        $firstName = $event->new_values['first_name'] ?? $event->old_values['first_name'] ?? null;
        $lastName = $event->new_values['last_name'] ?? $event->old_values['last_name'] ?? null;
        $name = trim("{$firstName} {$lastName}");

        return $name !== '' ? $name : __('audit.unknown_employee', ['id' => $event->employee_id]);
    }
}
