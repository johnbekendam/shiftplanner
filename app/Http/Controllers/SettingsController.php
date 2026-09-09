<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\ProductGroup;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'competences' => $this->listWithHolderCount(Competence::query()),
            'productGroups' => $this->listWithHolderCount(ProductGroup::query()),
            'businessLines' => $this->businessLines(),
        ]);
    }

    private function listWithHolderCount($query): array
    {
        return $query
            ->withCount('employees')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'position' => $row->position,
                'holder_count' => $row->employees_count,
            ])
            ->all();
    }

    private function businessLines(): array
    {
        return BusinessLine::query()
            ->withCount('employees')
            ->get()
            ->map(fn (BusinessLine $line) => [
                ...$line->toPayload(),
                'employee_count' => $line->employees_count,
            ])
            ->all();
    }
}
