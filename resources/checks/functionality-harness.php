<?php

/**
 * Standalone smoke-test harness for the review "functionality" check.
 *
 * Loads a plugin's main file with recording stubs of the OnePagerCMS hook and
 * section-type APIs (see onepagercms system/hooks.php and system/sections.php —
 * keep the stubs in sync when the CMS API changes) and reports what the plugin
 * registered as a single JSON line on stdout.
 *
 * Usage: php functionality-harness.php <plugin-dir> <main-file> <slug>
 *
 * Runs plain PHP without Laravel so it can execute under a restricted
 * subprocess (disable_functions, open_basedir, no url includes).
 */
if ($argc < 4) {
    echo json_encode(['ok' => false, 'error' => 'usage: harness <plugin-dir> <main-file> <slug>']), PHP_EOL;
    exit(1);
}

[, $pluginDir, $mainFile, $slug] = $argv;

define('OPCMS_BOOTSTRAPPED', true);
define('OPCMS_ROOT', $pluginDir);
define('OPCMS_VERSION', '1.2.0');

$GLOBALS['__check_actions'] = [];
$GLOBALS['__check_filters'] = [];
$GLOBALS['__check_section_types'] = [];
$GLOBALS['__check_rejected_section_types'] = [];
$GLOBALS['__check_finished'] = false;

function add_action(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['__check_actions'][] = $hook;
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['__check_filters'][] = $hook;
}

function do_action(string $hook, ...$args): void {}

function apply_filters(string $hook, $value, ...$args)
{
    return $value;
}

function remove_action(string $hook, callable $callback): void {}

function remove_filter(string $hook, callable $callback): void {}

// Mirrors the validation in onepagercms system/sections.php.
function opcms_register_section_type(string $type, array $config): bool
{
    $reject = function (string $reason) use ($type): bool {
        $GLOBALS['__check_rejected_section_types'][] = "{$type}: {$reason}";

        return false;
    };

    if (! preg_match('/^[a-z0-9][a-z0-9\-]{1,49}$/', $type)) {
        return $reject('invalid type name');
    }
    if (in_array($type, ['standard', 'icons', 'contact'], true)) {
        return $reject('collides with a built-in section type');
    }
    if (isset($GLOBALS['__check_section_types'][$type])) {
        return $reject('already registered');
    }
    if (! isset($config['label']) || ! is_string($config['label']) || $config['label'] === '') {
        return $reject('missing label');
    }
    if (! isset($config['build']) || ! is_callable($config['build'])) {
        return $reject('missing build callable');
    }
    if (isset($config['render']) && ! is_callable($config['render'])) {
        return $reject('render is not callable');
    }
    if (! isset($config['form_url']) || ! is_string($config['form_url']) || $config['form_url'] === '') {
        return $reject('missing form_url');
    }

    $GLOBALS['__check_section_types'][$type] = $config;

    return true;
}

function opcms_get_section_types(): array
{
    return $GLOBALS['__check_section_types'];
}

function opcms_get_section_type(string $type): ?array
{
    return $GLOBALS['__check_section_types'][$type] ?? null;
}

function __check_report(?string $error, int $outputBytes): void
{
    echo json_encode([
        'ok' => $error === null,
        'actions' => array_values($GLOBALS['__check_actions']),
        'filters' => array_values($GLOBALS['__check_filters']),
        'section_types' => array_keys($GLOBALS['__check_section_types']),
        'rejected_section_types' => array_values($GLOBALS['__check_rejected_section_types']),
        'output_bytes' => $outputBytes,
        'error' => $error,
    ]), PHP_EOL;
}

// Fatal errors abort the include below without unwinding; report them here.
register_shutdown_function(function (): void {
    if ($GLOBALS['__check_finished']) {
        return;
    }
    $output = '';
    while (ob_get_level() > 0) {
        $output = ob_get_clean().$output;
    }
    $last = error_get_last();
    $error = $last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
        ? "{$last['message']} in {$last['file']}:{$last['line']}"
        : 'The main file terminated unexpectedly.';
    __check_report($error, strlen($output));
});

$mainPath = rtrim($pluginDir, '/').'/'.$mainFile;
if (! is_file($mainPath)) {
    $GLOBALS['__check_finished'] = true;
    __check_report("Main file {$mainFile} not found in the archive.", 0);
    exit(1);
}

$error = null;
ob_start();
try {
    include $mainPath;
} catch (Throwable $e) {
    $error = get_class($e).': '.$e->getMessage();
}
$output = ob_get_clean();

$GLOBALS['__check_finished'] = true;
__check_report($error, strlen($output));
exit($error === null ? 0 : 1);
