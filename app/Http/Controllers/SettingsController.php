<?php

namespace App\Http\Controllers;

use App\Models\Competence;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $competences = Competence::query()
            ->withCount('employees')
            ->get()
            ->map(fn (Competence $competence) => [
                'id' => $competence->id,
                'name' => $competence->name,
                'position' => $competence->position,
                'holder_count' => $competence->employees_count,
            ])
            ->all();

        return Inertia::render('Settings/Index', [
            'competences' => $competences,
        ]);
    }
}
