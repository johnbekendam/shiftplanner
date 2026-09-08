@php
  $isDark = request()->cookie('darkMode') === '1';
@endphp
<!DOCTYPE html>
<html class="{{ $isDark ? 'dark' : '' }} h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>{{ config('app.name') }}</title>

  <script>
    (function() {
      function isDark() {
        return localStorage.getItem('darkMode') === 'true' ||
          (localStorage.getItem('darkMode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
      }
      function syncCookie(dark) {
        document.cookie = 'darkMode=' + (dark ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax';
      }
      syncCookie(isDark());
      document.documentElement.classList.toggle('dark', isDark());
      window.toggleDarkMode = function() {
        var dark = !isDark();
        localStorage.setItem('darkMode', dark ? 'true' : 'false');
        syncCookie(dark);
        document.documentElement.classList.toggle('dark', dark);
      };
    })();
  </script>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  {{-- Always emitted: renderCss() falls back to ThemeTokens::COLOR_DEFAULTS
       (the shipped ShiftPlanner theme) when no theme-tokens.json is saved. --}}
  <style id="theme-tokens">{!! (new \App\Services\ThemeTokens())->renderCss() !!}</style>
  @inertiaHead
</head>
<body class="h-full">
  @inertia
</body>
</html>
