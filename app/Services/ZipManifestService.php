<?php

namespace App\Services;

use ZipArchive;

/**
 * Validates uploaded extension archives with the same rules the CMS installer
 * applies (see onepagercms system/Installer.php): zip-slip protection, manifest
 * presence, slug/version format, plus a php -l lint over all contained PHP files.
 */
class ZipManifestService
{
    const MAX_ZIP_SIZE = 52428800; // 50 MB

    /**
     * @return array{ok: bool, error: ?string, manifest: ?array}
     */
    public function inspect(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            return $this->fail('The uploaded file could not be read.');
        }
        if (filesize($zipPath) > self::MAX_ZIP_SIZE) {
            return $this->fail('The archive exceeds the maximum size of 50 MB.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return $this->fail('The file is not a valid ZIP archive.');
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || $name === '') {
                $zip->close();
                return $this->fail('The archive contains unreadable entries.');
            }
            if (str_contains($name, '..') || str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/')) {
                $zip->close();
                return $this->fail('The archive contains unsafe file paths.');
            }
            $entries[] = $name;
        }

        $rootPrefix = $this->detectRootPrefix($entries);

        $manifest = null;
        foreach (['plugin.json', 'theme.json'] as $manifestName) {
            $content = $zip->getFromName($rootPrefix . $manifestName);
            if ($content !== false) {
                $manifest = json_decode($content, true);
                break;
            }
        }
        if (!is_array($manifest)) {
            $zip->close();
            return $this->fail('The archive does not contain a valid plugin.json or theme.json manifest.');
        }

        $manifestError = $this->validateManifest($manifest);
        if ($manifestError !== null) {
            $zip->close();
            return $this->fail($manifestError);
        }

        $lintError = $this->lintPhpFiles($zip, $entries);
        $zip->close();
        if ($lintError !== null) {
            return $this->fail($lintError);
        }

        return ['ok' => true, 'error' => null, 'manifest' => $manifest];
    }

    public function validateManifest(array $manifest): ?string
    {
        if (!isset($manifest['slug'], $manifest['type'], $manifest['name'], $manifest['version'])) {
            return 'The manifest must declare slug, type, name and version.';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{2,49}$/', $manifest['slug'])) {
            return 'The manifest slug may only contain lowercase letters, digits and dashes (3-50 characters).';
        }
        if (!in_array($manifest['type'], ['plugin', 'theme'], true)) {
            return 'The manifest type must be "plugin" or "theme".';
        }
        if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $manifest['version'])) {
            return 'The manifest version must look like 1.0 or 1.0.0.';
        }
        if ($manifest['type'] === 'plugin') {
            if (empty($manifest['main']) || !is_string($manifest['main'])
                || str_contains($manifest['main'], '..') || str_starts_with($manifest['main'], '/')
                || !str_ends_with($manifest['main'], '.php')) {
                return 'Plugin manifests must declare a valid "main" PHP entry file.';
            }
        }
        return null;
    }

    private function lintPhpFiles(ZipArchive $zip, array $entries): ?string
    {
        $phpBinary = PHP_BINARY ?: 'php';
        foreach ($entries as $entry) {
            if (!str_ends_with(strtolower($entry), '.php')) {
                continue;
            }
            $content = $zip->getFromName($entry);
            if ($content === false) {
                return "Could not read {$entry} from the archive.";
            }
            $tmp = tempnam(sys_get_temp_dir(), 'opcms-lint-');
            file_put_contents($tmp, $content);
            exec(escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $exitCode);
            unlink($tmp);
            if ($exitCode !== 0) {
                return "PHP syntax error in {$entry}.";
            }
        }
        return null;
    }

    private function detectRootPrefix(array $entries): string
    {
        $prefix = null;
        foreach ($entries as $entry) {
            $slash = strpos($entry, '/');
            if ($slash === false) {
                return '';
            }
            $top = substr($entry, 0, $slash + 1);
            if ($prefix === null) {
                $prefix = $top;
            } elseif ($prefix !== $top) {
                return '';
            }
        }
        return $prefix ?? '';
    }

    private function fail(string $error): array
    {
        return ['ok' => false, 'error' => $error, 'manifest' => null];
    }
}
