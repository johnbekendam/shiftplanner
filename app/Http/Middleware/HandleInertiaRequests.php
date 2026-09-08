<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        $manifest = public_path('build/manifest.json');

        return file_exists($manifest) ? md5_file($manifest) : null;
    }

    public function share(Request $request): array
    {
        $locale = App::getLocale();
        $langFile = lang_path($locale.'.json');

        return [
            ...parent::share($request),
            'locale' => $locale,
            'translations' => fn () => file_exists($langFile)
                ? json_decode(file_get_contents($langFile), true)
                : [],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'auth' => fn () => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'role', 'employee_id']),
            ],
            'appName' => config('app.name'),
        ];
    }
}
