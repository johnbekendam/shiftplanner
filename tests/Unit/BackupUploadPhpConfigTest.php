<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * EmployeeBackupController::import() validates uploads against `max:51200`
 * (50 MB) and tells the user "Upload a ShiftPlanner backup file no larger
 * than 50 MB." But the production image is stock `php:8.4-apache` with no
 * php.ini override, so PHP itself enforces its compiled-in defaults
 * (upload_max_filesize=2M, post_max_size=8M) before Laravel's validator
 * ever runs. A legitimately exported archive bigger than ~2 MB — which
 * real installs quickly exceed once shift_assignments/messages have data —
 * gets silently rejected by PHP, and the generic failure surfaces as the
 * same "no larger than 50 MB" message even though the file is well under
 * that limit.
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
        $dockerfile = file_get_contents($this->basePath('Dockerfile'));

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
