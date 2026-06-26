<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class DashboardUpdater
{
    /**
     * @return array{build_id: string, app_dir: string, webroot: string}
     */
    public function deploy(string $packagePath, string $token): array
    {
        $expectedToken = (string) env('DASHBOARD_UPDATE_TOKEN', '');
        if ($expectedToken === '') {
            throw new \RuntimeException('Dashboard updater is disabled. Set DASHBOARD_UPDATE_TOKEN in .env.');
        }

        if (! hash_equals($expectedToken, $token)) {
            throw new \RuntimeException('Invalid update token.');
        }

        $packagePath = trim($packagePath);
        if ($packagePath === '') {
            throw new \RuntimeException('Missing update package.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($packagePath)) {
            throw new \RuntimeException('Update package not found on disk.');
        }

        $zipFullPath = $disk->path($packagePath);
        if (! is_file($zipFullPath)) {
            throw new \RuntimeException('Update package is not a file.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive PHP extension is not available on this server.');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipFullPath);
        if ($openResult !== true) {
            throw new \RuntimeException('Failed to open zip (code: '.$openResult.').');
        }

        try {
            $this->assertZipLooksLikeShaghillaPackage($zip);

            $tmpRelative = 'updates/tmp/'.Str::uuid()->toString();
            $tmpDir = $disk->path($tmpRelative);
            File::ensureDirectoryExists($tmpDir);

            $this->safeExtract($zip, $tmpDir);

            $extractedApp = $tmpDir.'/app';
            $extractedPublic = $tmpDir.'/public_html';

            if (! is_dir($extractedApp) || ! is_dir($extractedPublic)) {
                throw new \RuntimeException('Zip extracted but missing app/ or public_html/ directory.');
            }

            $buildId = self::readBuildId($extractedApp);
            if ($buildId === null) {
                $buildId = now()->format('Ymd_His');
            }

            $webroot = public_path();
            $publicHtmlDir = $this->findPublicHtmlDir($webroot);
            $homeDir = dirname($publicHtmlDir);

            if (! is_dir($homeDir) || ! is_writable($homeDir)) {
                throw new \RuntimeException('Home directory is not writable: '.$homeDir);
            }

            $targetAppDir = $this->pickNewAppDir($homeDir, $buildId);

            if (! File::moveDirectory($extractedApp, $targetAppDir)) {
                throw new \RuntimeException('Failed to move app directory into place.');
            }

            $currentEnv = base_path('.env');
            if (! is_file($currentEnv)) {
                throw new \RuntimeException('Current .env not found. Aborting to avoid deploying without environment.');
            }
            File::copy($currentEnv, $targetAppDir.'/.env');

            $this->assertAppBootFilesExist($targetAppDir);

            $this->syncPublicHtml($extractedPublic, $webroot);

            $this->writeAppRootOverride($webroot, $targetAppDir);

            // Cleanup extracted temp directory. Keep the uploaded zip in case we need to retry.
            File::deleteDirectory($tmpDir);

            return [
                'build_id' => $buildId,
                'app_dir' => $targetAppDir,
                'webroot' => $webroot,
            ];
        } finally {
            $zip->close();
        }
    }

    public static function readBuildId(string $appRoot): ?string
    {
        $stampFile = rtrim($appRoot, '/').'/'.'.shaghilla-build-id';
        if (! is_file($stampFile)) {
            return null;
        }

        $value = trim((string) file_get_contents($stampFile));
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d{8}_\d{6}$/', $value)) {
            return null;
        }

        return $value;
    }

    public static function readAppRootOverride(string $webroot): ?string
    {
        $overrideFile = rtrim($webroot, '/').'/'.'.app-root';
        if (! is_file($overrideFile)) {
            return null;
        }

        $value = trim((string) file_get_contents($overrideFile));
        return $value !== '' ? $value : null;
    }

    private function assertZipLooksLikeShaghillaPackage(ZipArchive $zip): void
    {
        $hasApp = false;
        $hasPublic = false;
        $hasVendorAutoload = false;
        $hasBootstrap = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            $name = str_replace('\\', '/', $name);

            if (str_starts_with($name, 'app/')) {
                $hasApp = true;
            }
            if (str_starts_with($name, 'public_html/')) {
                $hasPublic = true;
            }
            if ($name === 'app/vendor/autoload.php') {
                $hasVendorAutoload = true;
            }
            if ($name === 'app/bootstrap/app.php') {
                $hasBootstrap = true;
            }
        }

        if (! $hasApp || ! $hasPublic) {
            throw new \RuntimeException('Invalid zip: expected top-level app/ and public_html/ folders.');
        }

        if (! $hasVendorAutoload || ! $hasBootstrap) {
            throw new \RuntimeException('Invalid zip: missing app/vendor/autoload.php or app/bootstrap/app.php (did you build with vendor included?).');
        }
    }

    private function safeExtract(ZipArchive $zip, string $destDir): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            $name = str_replace('\\', '/', $name);

            if (str_starts_with($name, '/')) {
                throw new \RuntimeException('Invalid zip entry: absolute paths are not allowed.');
            }

            if (str_contains($name, '..')) {
                throw new \RuntimeException('Invalid zip entry: path traversal detected.');
            }

            if (! (str_starts_with($name, 'app/') || str_starts_with($name, 'public_html/'))) {
                continue;
            }

            if (! $zip->extractTo($destDir, [$name])) {
                throw new \RuntimeException('Failed to extract zip entry: '.$name);
            }
        }
    }

    private function assertAppBootFilesExist(string $appDir): void
    {
        foreach ([
            $appDir.'/vendor/autoload.php',
            $appDir.'/bootstrap/app.php',
            $appDir.'/.env',
        ] as $required) {
            if (! is_file($required)) {
                throw new \RuntimeException('Deployed app is missing required file: '.$required);
            }
        }
    }

    private function findPublicHtmlDir(string $webroot): string
    {
        $dir = realpath($webroot) ?: $webroot;

        for ($i = 0; $i < 6; $i++) {
            if (basename($dir) === 'public_html') {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return realpath($webroot) ?: $webroot;
    }

    private function pickNewAppDir(string $homeDir, string $buildId): string
    {
        $buildId = preg_replace('/[^0-9_]/', '', $buildId) ?: now()->format('Ymd_His');
        $base = rtrim($homeDir, '/').'/shaghilla_app_'.$buildId;
        $candidate = $base;

        $i = 1;
        while (is_dir($candidate)) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }

    private function syncPublicHtml(string $sourcePublicHtmlDir, string $webroot): void
    {
        $sourcePublicHtmlDir = rtrim($sourcePublicHtmlDir, '/');
        $webroot = rtrim($webroot, '/');

        if (! is_dir($webroot) || ! is_writable($webroot)) {
            throw new \RuntimeException('Webroot is not writable: '.$webroot);
        }

        $items = scandir($sourcePublicHtmlDir);
        if (! is_array($items)) {
            throw new \RuntimeException('Failed to read extracted public_html directory.');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Preserve runtime uploads and the override file.
            if ($item === 'uploads' || $item === '.app-root') {
                continue;
            }

            $src = $sourcePublicHtmlDir.'/'.$item;
            $dst = $webroot.'/'.$item;

            if (is_dir($src)) {
                File::ensureDirectoryExists($dst);
                if (! File::copyDirectory($src, $dst)) {
                    throw new \RuntimeException('Failed to copy directory into webroot: '.$item);
                }
                continue;
            }

            if (! File::copy($src, $dst)) {
                throw new \RuntimeException('Failed to copy file into webroot: '.$item);
            }
        }
    }

    private function writeAppRootOverride(string $webroot, string $appDir): void
    {
        $overrideFile = rtrim($webroot, '/').'/'.'.app-root';
        File::put($overrideFile, $appDir);
    }
}

