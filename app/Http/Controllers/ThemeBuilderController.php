<?php

namespace App\Http\Controllers;

use App\Services\ThemeTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThemeBuilderController extends Controller
{
    public function index(): Response
    {
        $service = new ThemeTokens;
        $state = $service->load();

        return Inertia::render('ThemeBuilder', [
            'categories' => $service->colorCategories(),
            'colors' => $state['colors'],
            'defaultColors' => $service->colorDefaults(),
            'brandFamily' => $state['brand_family'],
            'defaultBrandFamily' => $service->brandFamilyDefault(),
            'brandFamilies' => $service->brandFamilies(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $service = new ThemeTokens;
        $data = $request->validate([
            'colors' => 'required|array',
            'brand_family' => ['nullable', 'string', Rule::in($service->brandFamilies())],
        ]);
        Storage::put('theme-tokens.json', json_encode([
            'brand_family' => $data['brand_family'] ?? $service->brandFamilyDefault(),
            'colors' => $data['colors'],
        ], JSON_PRETTY_PRINT));

        return response()->json(['ok' => true]);
    }

    public function reset(): JsonResponse
    {
        Storage::delete('theme-tokens.json');

        return response()->json(['ok' => true]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|file|mimes:svg,png,jpg,jpeg|max:2048',
            'logo_png' => 'nullable|file|mimes:png|max:2048',
        ]);

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension());

        foreach (glob(public_path('images/logo-custom.*')) ?: [] as $old) {
            unlink($old);
        }
        @unlink(public_path('images/logo-custom-email.png'));

        $filename = "logo-custom.{$ext}";
        $file->move(public_path('images'), $filename);

        $emailFilename = null;
        if ($request->hasFile('logo_png')) {
            $emailFilename = 'logo-custom-email.png';
            $request->file('logo_png')->move(public_path('images'), $emailFilename);
        }

        Storage::put('logo.json', json_encode(['filename' => $filename, 'email_filename' => $emailFilename]));

        return response()->json(['url' => asset("images/{$filename}")]);
    }

    public function deleteLogo(): JsonResponse
    {
        foreach (glob(public_path('images/logo-custom.*')) ?: [] as $old) {
            unlink($old);
        }
        @unlink(public_path('images/logo-custom-email.png'));
        Storage::delete('logo.json');

        return response()->json(['ok' => true]);
    }
}
