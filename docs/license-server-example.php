<?php
/**
 * Reference license/update server for paid OnePagerCMS extensions.
 *
 * Drop this file on your own server and point the "update_endpoint" in your
 * plugin.json / theme.json at its URL. OnePagerCMS installations will call it
 * with these actions:
 *
 *   GET ?opcms_action=check_update&slug=<slug>&version=<installed>&license=<key>&site=<host>
 *     → 200 {"slug": "...", "new_version": "1.1.0", "package": "https://.../my-plugin-1.1.0.zip?license=...",
 *            "changelog": "...", "requires_opcms": "1.2.0"}
 *     → 200 {"success": false, "error": "invalid"}   when the license is not valid
 *
 *   GET ?opcms_action=activate_license&slug=<slug>&license=<key>&site=<host>
 *     → 200 {"success": true}
 *     → 200 {"success": false, "error": "expired|invalid|site_limit"}
 *
 *   GET ?opcms_action=download&license=<key>   (implementation detail of this example:
 *     the "package" URL points back at this script, which streams the ZIP after
 *     re-checking the license)
 *
 * Replace the configuration below with your own storage/database.
 */

$config = array(
    'slug' => 'my-pro-plugin',
    'latest_version' => '1.1.0',
    'requires_opcms' => '1.2.0',
    'changelog' => 'Latest improvements.',
    // ZIP of the latest version, relative to this script:
    'package_file' => __DIR__ . '/my-pro-plugin-latest.zip',
    // Valid license keys. In a real implementation, check your shop database.
    'valid_licenses' => array('DEMO-1234-5678-9000'),
);

header('Content-Type: application/json');

$action = isset($_GET['opcms_action']) ? $_GET['opcms_action'] : '';
$license = isset($_GET['license']) ? $_GET['license'] : '';
$licenseValid = in_array($license, $config['valid_licenses'], true);

if ($action === 'activate_license') {
    echo json_encode($licenseValid ? array('success' => true) : array('success' => false, 'error' => 'invalid'));
    exit;
}

if ($action === 'check_update') {
    if (!$licenseValid) {
        echo json_encode(array('success' => false, 'error' => 'invalid'));
        exit;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $self = $scheme . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    echo json_encode(array(
        'slug' => $config['slug'],
        'new_version' => $config['latest_version'],
        'package' => $self . '?opcms_action=download&license=' . rawurlencode($license),
        'changelog' => $config['changelog'],
        'requires_opcms' => $config['requires_opcms'],
    ));
    exit;
}

if ($action === 'download') {
    if (!$licenseValid || !is_file($config['package_file'])) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'invalid'));
        exit;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($config['package_file']) . '"');
    header('Content-Length: ' . filesize($config['package_file']));
    readfile($config['package_file']);
    exit;
}

http_response_code(400);
echo json_encode(array('success' => false, 'error' => 'unknown_action'));
