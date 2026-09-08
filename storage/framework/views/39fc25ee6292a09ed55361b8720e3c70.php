<?php
  $isDark = request()->cookie('darkMode') === '1';
?>
<!DOCTYPE html>
<html class="<?php echo e($isDark ? 'dark' : ''); ?> h-full" lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>" />
  <title><?php echo e(config('app.name')); ?></title>

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

  <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
  
  <style id="theme-tokens"><?php echo (new \App\Services\ThemeTokens())->renderCss(); ?></style>
  <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
</head>
<body class="h-full">
  <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } ?>
</body>
</html>
<?php /**PATH /Users/jb/Development/Prodrive/shiftplanner/resources/views/app.blade.php ENDPATH**/ ?>