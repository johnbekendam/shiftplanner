<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * EmployeeBackupController::import() validates uploads against `max:51200`
 * (50 MB) and tells the user "Upload a ShiftPlanner backup file no larger
 * than 50 MB." But neither the production image (stock `php:8.4-apache`)
 * nor local dev (`php artisan serve` against the host's own php.ini) had
 * any php.ini override, so PHP itself enforced its compiled-in defaults
 * (upload_max_filesize=2M, post_max_size=8M) before Laravel's validator
 * ever ran. A legitimately exported archive bigger than ~2 MB — which real
 * installs quickly exceed once shift_assignments/messages have data — was
 * silently rejected by PHP, and the generic failure surfaced as the same
 * "no larger than 50 MB" message even though the file was well under that
 * limit.
 */
class BackupUploadPhpConfigTest extends TestCase
{
    private const CONTROLLER_MAX_KILOBYTES = 51200;

    private function basePath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    public function test_dockerfile_installs_a_php_ini_override_for_uploads(): void
    {
        $dockerfile = file_get_contents($this->basePath('docker/Dockerfile'));

        $this->assertMatchesRegularExpression(
            '#COPY\s+\S+\s+/usr/local/etc/php/conf\.d/\S+\.ini#',
            $dockerfile,
            'Dockerfile must copy a php.ini override into conf.d, otherwise PHP falls back to its '
            .'compiled-in upload_max_filesize=2M/post_max_size=8M defaults.'
        );
    }

    public function test_php_ini_override_allows_uploads_up_to_the_controllers_limit(): void
    {
        $iniPath = $this->basePath('docker/php/uploads.ini');

        $this->assertFileExists($iniPath, 'Expected a php.ini override at docker/php/uploads.ini.');

        $settings = parse_ini_file($iniPath);
        $requiredBytes = self::CONTROLLER_MAX_KILOBYTES * 1024;

        $uploadMaxFilesize = $this->bytesFromIniShorthand($settings['upload_max_filesize'] ?? '0');
        $postMaxSize = $this->bytesFromIniShorthand($settings['post_max_size'] ?? '0');

        $this->assertGreaterThanOrEqual(
            $requiredBytes,
            $uploadMaxFilesize,
            'upload_max_filesize must allow the full 50 MB the controller validates against.'
        );

        $this->assertGreaterThan(
            $uploadMaxFilesize,
            $postMaxSize,
            'post_max_size must exceed upload_max_filesize to leave room for multipart form overhead.'
        );
    }

    public function test_composer_dev_script_points_local_serve_at_the_same_ini_override(): void
    {
        $composerJson = json_decode(file_get_contents($this->basePath('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $devScript = implode("\n", $composerJson['scripts']['dev'] ?? []);

        $this->assertStringContainsString(
            'PHP_INI_SCAN_DIR',
            $devScript,
            'The "dev" composer script must point PHP_INI_SCAN_DIR at docker/php so `composer run dev` '
            .'(php artisan serve against the host php.ini) allows the same upload sizes as production. '
            .'Passing -d flags to `php artisan serve` itself does not work: Laravel\'s ServeCommand '
            .'spawns a fresh `php -S` child process that does not inherit them.'
        );

        $this->assertStringContainsString('docker/php', $devScript);
    }

    private function bytesFromIniShorthand(string $value): int
    {
        $value = trim($value);
        $unit = strtoupper(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => (int) $value,
        };
    }
}
