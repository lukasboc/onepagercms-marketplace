<?php

namespace App\Services;

use App\Models\ItemVersion;
use App\Models\VersionCheck;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Runs the individual admin review checks against an uploaded extension ZIP.
 *
 * All results are advisory: they are persisted per (version, check) for the
 * reviewer to inspect and never approve or reject anything automatically.
 * The functionality check executes untrusted plugin code in a subprocess;
 * disable_functions/open_basedir/timeout block the obvious escape paths but
 * this is best-effort hardening, not a security sandbox — the check is meant
 * to be triggered manually by an admin during human review.
 */
class VersionCheckService
{
    /**
     * Hooks fired by the CMS. Keep in sync with the onepagercms repo
     * (grep for do_action/apply_filters; see system/hooks.php for the API).
     */
    const CMS_ACTIONS = [
        'opcms_before_sections', 'opcms_after_sections',
        'opcms_before_nav', 'opcms_after_nav',
        'opcms_footer', 'opcms_head', 'opcms_body_end',
        'opcms_admin_head', 'opcms_admin_footer',
    ];

    const CMS_FILTERS = [
        'opcms_sections', 'opcms_section_html', 'opcms_custom_css',
        'opcms_admin_nav_items', 'opcms_admin_pages', 'opcms_extension_handlers',
    ];

    /** Lifecycle hooks are only valid with the plugin's own slug appended. */
    const LIFECYCLE_PREFIXES = ['opcms_activate_', 'opcms_deactivate_', 'opcms_uninstall_'];

    const HOOK_SUBSCRIBE_FUNCTIONS = ['add_action', 'add_filter'];

    const HOOK_FIRE_FUNCTIONS = ['do_action', 'apply_filters'];

    public function __construct(private ZipManifestService $manifests) {}

    public function run(ItemVersion $version, string $check, ?int $runnerId): VersionCheck
    {
        [$status, $findings] = $this->execute($version, $check);

        return VersionCheck::updateOrCreate(
            ['item_version_id' => $version->id, 'check' => $check],
            ['runner_id' => $runnerId, 'status' => $status, 'findings' => $findings],
        );
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function execute(ItemVersion $version, string $check): array
    {
        $pluginOnly = [VersionCheck::CHECK_HOOKS, VersionCheck::CHECK_UNINSTALL, VersionCheck::CHECK_FUNCTIONALITY];
        if ($version->item->type !== 'plugin' && in_array($check, $pluginOnly, true)) {
            return [VersionCheck::STATUS_SKIPPED, ['This check only applies to plugins.']];
        }

        $themeOnly = [VersionCheck::CHECK_THEME_OPTIONS];
        if ($version->item->type !== 'theme' && in_array($check, $themeOnly, true)) {
            return [VersionCheck::STATUS_SKIPPED, ['This check only applies to themes.']];
        }

        $zipPath = $version->zip_path === null ? null : Storage::disk('local')->path($version->zip_path);
        if ($zipPath === null || ! is_file($zipPath)) {
            return [VersionCheck::STATUS_FAILED, ['The review ZIP is missing from storage.']];
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return [VersionCheck::STATUS_FAILED, ['The file is not a valid ZIP archive.']];
        }

        try {
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name !== false && $name !== '') {
                    $entries[] = $name;
                }
            }
            $rootPrefix = $this->manifests->detectRootPrefix($entries);
            $manifest = $this->readManifest($zip, $rootPrefix);
            $slug = is_array($manifest) && isset($manifest['slug']) && is_string($manifest['slug'])
                ? $manifest['slug']
                : $version->item->slug;

            return match ($check) {
                VersionCheck::CHECK_MANIFEST => $this->checkManifest($zipPath, $version),
                VersionCheck::CHECK_HOOKS => $this->checkHooks($zip, $entries, $slug),
                VersionCheck::CHECK_UNINSTALL => $this->checkUninstall($zip, $entries, $slug),
                VersionCheck::CHECK_MALWARE => $this->checkMalware($zip, $entries),
                VersionCheck::CHECK_FUNCTIONALITY => $this->checkFunctionality($zip, $rootPrefix, $manifest, $slug),
                VersionCheck::CHECK_THEME_OPTIONS => $this->checkThemeOptions($zip, $entries, $manifest, $slug),
            };
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function checkManifest(string $zipPath, ItemVersion $version): array
    {
        $inspection = $this->manifests->inspect($zipPath);
        if (! $inspection['ok']) {
            return [VersionCheck::STATUS_FAILED, [$inspection['error']]];
        }

        $manifest = $inspection['manifest'];
        $failed = [];
        $warnings = [];

        foreach (['description', 'author', 'requires_opcms'] as $field) {
            if (empty($manifest[$field])) {
                $warnings[] = "The manifest does not declare \"{$field}\" (recommended).";
            }
        }
        if ($version->item->is_paid && empty($manifest['update_endpoint'])) {
            $failed[] = 'Paid items must declare an "update_endpoint" in the manifest.';
        }

        if ($failed !== [] || $warnings !== []) {
            return [$failed !== [] ? VersionCheck::STATUS_FAILED : VersionCheck::STATUS_WARNING, array_merge($failed, $warnings)];
        }

        $main = $manifest['type'] === 'plugin' ? ", main {$manifest['main']}" : '';

        return [VersionCheck::STATUS_PASSED, ["Manifest is complete: {$manifest['type']} {$manifest['slug']} {$manifest['version']}{$main}."]];
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function checkHooks(ZipArchive $zip, array $entries, string $slug): array
    {
        $failed = [];
        $warnings = [];
        $info = [];
        $subscriptions = 0;

        $callsByFile = [];
        $customHooks = [];
        foreach ($this->phpSources($zip, $entries) as $entry => $source) {
            $calls = $this->extractHookCalls($source);
            $callsByFile[$entry] = $calls;
            foreach ($calls as $call) {
                if ($call['hook'] !== null && in_array($call['fn'], self::HOOK_FIRE_FUNCTIONS, true)) {
                    $customHooks[] = $call['hook'];
                }
            }
        }

        foreach ($callsByFile as $entry => $calls) {
            foreach ($calls as $call) {
                if (! in_array($call['fn'], self::HOOK_SUBSCRIBE_FUNCTIONS, true)) {
                    continue;
                }
                $subscriptions++;
                $where = "{$entry}:{$call['line']}";

                if ($call['hook'] === null) {
                    $warnings[] = "{$where} — {$call['fn']}() with a dynamic hook name; verify manually.";

                    continue;
                }

                $hook = $call['hook'];
                $lifecycle = $this->lifecyclePrefix($hook);

                if ($lifecycle !== null) {
                    if ($hook !== $lifecycle.$slug) {
                        $failed[] = "{$where} — subscribes to lifecycle hook \"{$hook}\" of a foreign slug (expected \"{$lifecycle}{$slug}\").";
                    } elseif ($call['fn'] !== 'add_action') {
                        $warnings[] = "{$where} — lifecycle hook \"{$hook}\" must be subscribed with add_action(), not {$call['fn']}().";
                    }

                    continue;
                }

                $isCmsAction = in_array($hook, self::CMS_ACTIONS, true);
                $isCmsFilter = in_array($hook, self::CMS_FILTERS, true);

                if ($isCmsAction || $isCmsFilter) {
                    $expected = $isCmsAction ? 'add_action' : 'add_filter';
                    if ($call['fn'] !== $expected) {
                        $warnings[] = "{$where} — \"{$hook}\" is a CMS ".($isCmsAction ? 'action' : 'filter').", subscribe with {$expected}() instead of {$call['fn']}().";
                    }

                    continue;
                }

                if (in_array($hook, $customHooks, true)) {
                    $info[] = "{$where} — \"{$hook}\" is a custom hook fired by the plugin itself.";

                    continue;
                }

                $failed[] = "{$where} — subscribes to unknown hook \"{$hook}\".";
            }
        }

        if ($subscriptions === 0) {
            $warnings[] = 'The plugin does not subscribe to any hooks.';
        }

        if ($failed !== []) {
            return [VersionCheck::STATUS_FAILED, array_merge($failed, $warnings, $info)];
        }
        if ($warnings !== []) {
            return [VersionCheck::STATUS_WARNING, array_merge($warnings, $info)];
        }

        return [VersionCheck::STATUS_PASSED, array_merge(["All {$subscriptions} hook subscription(s) target known CMS hooks."], $info)];
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function checkUninstall(ZipArchive $zip, array $entries, string $slug): array
    {
        $sources = $this->phpSources($zip, $entries);
        $allSource = implode("\n", $sources);

        $createsTables = preg_match('/\bCREATE\s+TABLE\b/i', $allSource) === 1;
        $dropsTables = preg_match('/\bDROP\s+TABLE\b/i', $allSource) === 1;
        $registersSectionType = preg_match('/\bopcms_register_section_type\s*\(/', $allSource) === 1;
        $deletesSectionEntries = str_contains($allSource, 'deleteSectionEntriesByType');

        $uninstallHook = 'opcms_uninstall_'.$slug;
        $hasUninstall = false;
        foreach ($sources as $source) {
            foreach ($this->extractHookCalls($source) as $call) {
                if ($call['fn'] === 'add_action' && $call['hook'] === $uninstallHook) {
                    $hasUninstall = true;
                    break 2;
                }
            }
        }

        $needsCleanup = $createsTables || $registersSectionType;

        if (! $needsCleanup) {
            $note = $hasUninstall
                ? "Uninstall hook \"{$uninstallHook}\" is registered."
                : 'No persistent artifacts detected (tables, section types); an uninstall hook is not required.';

            return [VersionCheck::STATUS_PASSED, [$note]];
        }

        if (! $hasUninstall) {
            $reasons = [];
            if ($createsTables) {
                $reasons[] = 'creates database tables';
            }
            if ($registersSectionType) {
                $reasons[] = 'registers section types';
            }

            return [VersionCheck::STATUS_FAILED, [
                'The plugin '.implode(' and ', $reasons)." but does not register an \"{$uninstallHook}\" action.",
            ]];
        }

        $warnings = [];
        if ($createsTables && ! $dropsTables) {
            $warnings[] = 'The plugin creates database tables but no DROP TABLE statement was found; verify the uninstall callback cleans them up.';
        }
        if ($registersSectionType && ! $deletesSectionEntries) {
            $warnings[] = 'The plugin registers section types but never calls deleteSectionEntriesByType(); verify orphaned section rows are cleaned up on uninstall.';
        }

        if ($warnings !== []) {
            return [VersionCheck::STATUS_WARNING, $warnings];
        }

        return [VersionCheck::STATUS_PASSED, ["Uninstall hook \"{$uninstallHook}\" is registered and cleanup statements are present."]];
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function checkMalware(ZipArchive $zip, array $entries): array
    {
        $high = [];
        $medium = [];

        $highPatterns = [
            '/\beval\s*\(/i' => 'eval() call',
            '/\b(?:shell_exec|exec|system|passthru|proc_open|popen|pcntl_exec)\s*\(/i' => 'command execution function',
            '/\bassert\s*\(\s*\$/i' => 'assert() on a variable (code execution)',
            '/\bcreate_function\s*\(/i' => 'create_function() (code execution)',
            '/\b(?:include|require)(?:_once)?\s*\(?\s*[\'"]https?:/i' => 'remote code inclusion',
            '/\$\w+\s*\(\s*(?:base64_decode|gzinflate|str_rot13|gzuncompress)\b/i' => 'variable function over a decoder (obfuscated execution)',
        ];
        $mediumPatterns = [
            '/\b(?:gzinflate|gzuncompress|gzdecode|str_rot13|hex2bin)\s*\(/i' => 'obfuscation/decoding function',
            '/[\'"][A-Za-z0-9+\/=]{200,}[\'"]/' => 'long base64-like string literal',
            '/(?:\\\\x[0-9a-f]{2}){10,}/i' => 'long hex-escaped payload',
            '/\b(?:curl_exec|curl_multi_exec|fsockopen|stream_socket_client)\s*\(/i' => 'remote connection function',
            '/\bfile_get_contents\s*\(\s*[\'"]https?:/i' => 'remote content fetch',
            '/\bfile_put_contents\s*\([^)]*\.php/i' => 'writes a PHP file',
        ];

        foreach ($this->phpSources($zip, $entries) as $entry => $source) {
            foreach ($highPatterns as $pattern => $label) {
                $this->collectMatches($source, $pattern, $entry, $label, $high);
            }
            foreach ($mediumPatterns as $pattern => $label) {
                $this->collectMatches($source, $pattern, $entry, $label, $medium);
            }

            if (preg_match('/\b(?:eval|assert)\s*\(/i', $source) === 1) {
                $this->collectMatches($source, '/\bbase64_decode\s*\(/i', $entry, 'base64_decode() combined with eval/assert in the same file', $high);
            } else {
                $this->collectMatches($source, '/\bbase64_decode\s*\(/i', $entry, 'base64_decode() call', $medium);
            }

            $this->collectPregReplaceEvalModifier($source, $entry, $high);
            $this->collectBacktickOperators($source, $entry, $high);
        }

        foreach ($entries as $entry) {
            if (str_ends_with($entry, '/') || str_ends_with(strtolower($entry), '.php')) {
                continue;
            }
            $content = $zip->getFromName($entry);
            if ($content !== false && str_contains($content, '<?php')) {
                $medium[] = "{$entry} — contains PHP code in a non-PHP file.";
            }
        }

        if ($high !== []) {
            return [VersionCheck::STATUS_FAILED, array_merge($high, $medium)];
        }
        if ($medium !== []) {
            return [VersionCheck::STATUS_WARNING, $medium];
        }

        return [VersionCheck::STATUS_PASSED, ['No suspicious code patterns found.']];
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function checkFunctionality(ZipArchive $zip, string $rootPrefix, ?array $manifest, string $slug): array
    {
        if (! is_array($manifest) || empty($manifest['main']) || ! is_string($manifest['main'])) {
            return [VersionCheck::STATUS_FAILED, ['Cannot determine the main file: the manifest is missing or does not declare "main".']];
        }

        $tempDir = $this->extractToTempDir($zip, $rootPrefix);
        if ($tempDir === null) {
            return [VersionCheck::STATUS_FAILED, ['Could not extract the archive for the smoke test.']];
        }

        $harness = resource_path('checks/functionality-harness.php');

        try {
            $result = Process::timeout(10)->run([
                PHP_BINARY,
                '-d', 'disable_functions=exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,curl_exec,curl_multi_exec,mail,fsockopen,pfsockopen,stream_socket_client',
                '-d', 'allow_url_fopen=0',
                '-d', 'allow_url_include=0',
                '-d', 'open_basedir='.$tempDir.PATH_SEPARATOR.dirname($harness),
                '-d', 'memory_limit=64M',
                '-d', 'display_errors=1',
                '-d', 'error_reporting=E_ALL',
                $harness,
                $tempDir,
                $manifest['main'],
                $slug,
            ]);
        } catch (ProcessTimedOutException) {
            return [VersionCheck::STATUS_FAILED, ['The main file did not finish loading within 10 seconds.']];
        } finally {
            File::deleteDirectory($tempDir);
        }

        $report = json_decode((string) strtok(trim($result->output()), "\n"), true);
        if (! is_array($report)) {
            $tail = mb_substr(trim($result->output()."\n".$result->errorOutput()), -1000);

            return [VersionCheck::STATUS_FAILED, ['The smoke test produced no readable result.'.($tail !== '' ? " Output: {$tail}" : '')]];
        }

        if ($report['ok'] !== true) {
            return [VersionCheck::STATUS_FAILED, ['Loading the main file failed: '.($report['error'] ?? 'unknown error')]];
        }

        $warnings = [];
        $info = [];

        $actions = count($report['actions'] ?? []);
        $filters = count($report['filters'] ?? []);
        $sectionTypes = $report['section_types'] ?? [];

        if ($actions + $filters + count($sectionTypes) === 0) {
            $warnings[] = 'The main file loads but registers no hooks or section types.';
        } else {
            $info[] = "Registered {$actions} action(s) and {$filters} filter(s)."
                .($sectionTypes !== [] ? ' Section types: '.implode(', ', $sectionTypes).'.' : '');
        }
        foreach ($report['rejected_section_types'] ?? [] as $rejected) {
            $warnings[] = "Section type registration rejected — {$rejected}.";
        }
        if (($report['output_bytes'] ?? 0) > 0) {
            $warnings[] = "The main file produces {$report['output_bytes']} byte(s) of output when loading; plugins should only register hooks at load time.";
        }

        if ($warnings !== []) {
            return [VersionCheck::STATUS_WARNING, array_merge($warnings, $info)];
        }

        return [VersionCheck::STATUS_PASSED, array_merge(['The main file loads without errors.'], $info)];
    }

    /**
     * Theme-only: verifies that every option declared in theme.json is
     * actually referenced by the theme's PHP code, and that no undeclared
     * option keys are read. Usage detection is heuristic (quoted key or the
     * full theme-option:<slug>:<key> settings literal), so mismatches are
     * reported as warnings for the reviewer, never as hard failures.
     *
     * @return array{0: string, 1: string[]}
     */
    private function checkThemeOptions(ZipArchive $zip, array $entries, ?array $manifest, string $slug): array
    {
        if (! is_array($manifest)) {
            return [VersionCheck::STATUS_FAILED, ['The theme.json manifest is missing or invalid.']];
        }

        $findings = [];
        $declared = [];

        $rawOptions = $manifest['options'] ?? [];
        if (! is_array($rawOptions)) {
            $findings[] = 'The manifest "options" entry is not an array.';
            $rawOptions = [];
        }

        foreach (array_values($rawOptions) as $index => $option) {
            $position = 'options['.$index.']';
            if (! is_array($option) || ! isset($option['key']) || ! is_string($option['key'])) {
                $findings[] = "{$position} has no valid \"key\" and will be ignored by the CMS.";

                continue;
            }
            $key = $option['key'];
            if (preg_match('/^[a-z0-9][a-z0-9\-]{0,49}$/', $key) !== 1) {
                $findings[] = "{$position} key \"{$key}\" does not match ^[a-z0-9][a-z0-9-]{0,49}$ and will be ignored by the CMS.";

                continue;
            }
            if (in_array($key, $declared, true)) {
                $findings[] = "Option key \"{$key}\" is declared more than once.";

                continue;
            }
            $type = $option['type'] ?? 'text';
            if (! in_array($type, ['color', 'text', 'select'], true)) {
                $findings[] = "Option \"{$key}\" has unknown type \"".(is_scalar($type) ? $type : gettype($type)).'"; the CMS will treat it as "text".';
            }
            if ($type === 'select') {
                $choices = $option['choices'] ?? [];
                if (! is_array($choices) || array_filter($choices, 'is_string') === []) {
                    $findings[] = "Select option \"{$key}\" has no valid \"choices\" and will be ignored by the CMS.";

                    continue;
                }
            }
            $declared[] = $key;
        }

        $sources = $this->phpSources($zip, $entries);
        $allSource = implode("\n", $sources);

        foreach ($declared as $key) {
            $quotedKey = preg_quote($key, '/');
            $used = str_contains($allSource, 'theme-option:'.$slug.':'.$key)
                || preg_match('/[\'"]'.$quotedKey.'[\'"]/', $allSource) === 1;
            if (! $used) {
                $findings[] = "Declared option \"{$key}\" is never referenced in the theme's PHP code.";
            }
        }

        foreach ($sources as $entry => $source) {
            $readKeys = [];
            if (preg_match_all('/theme-option:'.preg_quote($slug, '/').':([a-z0-9\-]+)/', $source, $matches) > 0) {
                $readKeys = $matches[1];
            }
            if (preg_match_all('/\bopcms_theme_option\s*\(\s*[\'"]([a-z0-9\-]+)[\'"]/', $source, $matches) > 0) {
                $readKeys = array_merge($readKeys, $matches[1]);
            }
            foreach (array_unique($readKeys) as $key) {
                if (! in_array($key, $declared, true)) {
                    $findings[] = "Option \"{$key}\" is read in {$entry} but not declared in theme.json.";
                }
            }
        }

        if ($findings !== []) {
            return [VersionCheck::STATUS_WARNING, $findings];
        }

        if ($declared === []) {
            return [VersionCheck::STATUS_PASSED, ['The theme declares no options.']];
        }

        return [VersionCheck::STATUS_PASSED, ['All '.count($declared).' declared option(s) are referenced in the theme code.']];
    }

    /**
     * @return array<string, string> map of entry name to PHP source
     */
    private function phpSources(ZipArchive $zip, array $entries): array
    {
        $sources = [];
        foreach ($entries as $entry) {
            if (! str_ends_with(strtolower($entry), '.php')) {
                continue;
            }
            $content = $zip->getFromName($entry);
            if ($content !== false) {
                $sources[$entry] = $content;
            }
        }

        return $sources;
    }

    /**
     * Token-based extraction of hook API calls. Returns one row per call:
     * ['fn' => 'add_action', 'hook' => 'opcms_head'|null, 'line' => 12],
     * where hook is null when the first argument is not a plain string literal.
     *
     * @return array<int, array{fn: string, hook: ?string, line: int}>
     */
    private function extractHookCalls(string $source): array
    {
        $functions = array_merge(self::HOOK_SUBSCRIBE_FUNCTIONS, self::HOOK_FIRE_FUNCTIONS);
        $calls = [];
        $tokens = token_get_all($source);

        foreach ($tokens as $i => $token) {
            if (! is_array($token) || $token[0] !== T_STRING || ! in_array(strtolower($token[1]), $functions, true)) {
                continue;
            }

            $prev = $this->adjacentMeaningfulToken($tokens, $i, -1);
            if (is_array($tokens[$prev] ?? null) && in_array($tokens[$prev][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            $open = $this->adjacentMeaningfulToken($tokens, $i, 1);
            if ($open === null || $tokens[$open] !== '(') {
                continue;
            }

            $hook = null;
            $first = $this->adjacentMeaningfulToken($tokens, $open, 1);
            if ($first !== null && is_array($tokens[$first]) && $tokens[$first][0] === T_CONSTANT_ENCAPSED_STRING) {
                $after = $this->adjacentMeaningfulToken($tokens, $first, 1);
                if ($after !== null && ($tokens[$after] === ',' || $tokens[$after] === ')')) {
                    $hook = substr($tokens[$first][1], 1, -1);
                }
            }

            $calls[] = ['fn' => strtolower($token[1]), 'hook' => $hook, 'line' => $token[2]];
        }

        return $calls;
    }

    private function adjacentMeaningfulToken(array $tokens, int $index, int $direction): ?int
    {
        for ($i = $index + $direction; isset($tokens[$i]); $i += $direction) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    private function lifecyclePrefix(string $hook): ?string
    {
        foreach (self::LIFECYCLE_PREFIXES as $prefix) {
            if (str_starts_with($hook, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    private function collectMatches(string $source, string $pattern, string $entry, string $label, array &$findings): void
    {
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }
        foreach ($matches[0] as [, $offset]) {
            $findings[] = "{$entry}:{$this->lineFromOffset($source, $offset)} — {$label}.";
        }
    }

    /** Flags preg_replace() calls whose pattern literal uses the deprecated /e (eval) modifier. */
    private function collectPregReplaceEvalModifier(string $source, string $entry, array &$findings): void
    {
        if (! preg_match_all('/\bpreg_replace\s*\(\s*([\'"])(.*?)\1/s', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return;
        }
        foreach ($matches as $match) {
            $pattern = $match[2][0];
            if ($pattern === '') {
                continue;
            }
            $delimiterEnd = strrpos($pattern, $pattern[0]);
            if ($delimiterEnd !== false && $delimiterEnd > 0 && str_contains(substr($pattern, $delimiterEnd + 1), 'e')) {
                $findings[] = "{$entry}:{$this->lineFromOffset($source, $match[0][1])} — preg_replace() with /e modifier (code execution).";
            }
        }
    }

    /** Detects the backtick shell-execution operator via tokens to avoid string-literal false positives. */
    private function collectBacktickOperators(string $source, string $entry, array &$findings): void
    {
        $line = 1;
        $inShellExec = false;
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                $line = $token[2];

                continue;
            }
            if ($token === '`') {
                if (! $inShellExec) {
                    $findings[] = "{$entry}:{$line} — backtick shell-execution operator.";
                }
                $inShellExec = ! $inShellExec;
            }
        }
    }

    private function lineFromOffset(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * Extracts the archive (stripping the root prefix) into a unique temp
     * directory. The caller is responsible for deleting it.
     */
    private function extractToTempDir(ZipArchive $zip, string $rootPrefix): ?string
    {
        $tempDir = sys_get_temp_dir().'/opcms-check-'.bin2hex(random_bytes(8));
        if (! mkdir($tempDir, 0700, true)) {
            return null;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || $name === '' || str_ends_with($name, '/')) {
                continue;
            }
            // Uploads passed the zip-slip scan at submission; re-check defensively.
            if (str_contains($name, '..') || str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/')) {
                continue;
            }
            $relative = $rootPrefix !== '' && str_starts_with($name, $rootPrefix)
                ? substr($name, strlen($rootPrefix))
                : $name;
            if ($relative === '') {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }
            $target = $tempDir.'/'.$relative;
            File::ensureDirectoryExists(dirname($target));
            file_put_contents($target, $content);
        }

        return $tempDir;
    }

    private function readManifest(ZipArchive $zip, string $rootPrefix): ?array
    {
        foreach (['plugin.json', 'theme.json'] as $manifestName) {
            $content = $zip->getFromName($rootPrefix.$manifestName);
            if ($content !== false) {
                $manifest = json_decode($content, true);

                return is_array($manifest) ? $manifest : null;
            }
        }

        return null;
    }
}
