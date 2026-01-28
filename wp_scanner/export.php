<?php
/**
 * Export data as JSON or CSV.
 *
 * Single asset:
 *   ?id=N                        → export single asset with its plugins/themes
 *
 * All WP assets:
 *   ?scope=wp                    → export all WordPress assets
 *
 * Assets by component:
 *   ?scope=component&comp_type=plugin&slug=xxx  → assets using plugin xxx
 *   ?scope=component&comp_type=theme&slug=xxx   → assets using theme xxx
 *
 * All assets (default):
 *   (no params)                  → export everything
 *
 * Format:
 *   ?type=json (default) or ?type=csv
 */
require_once __DIR__ . '/includes/config.php';
init_db();

$id        = isset($_GET['id']) ? (int) $_GET['id'] : null;
$type      = $_GET['type'] ?? 'json';
$scope     = trim($_GET['scope'] ?? '');
$comp_type = trim($_GET['comp_type'] ?? '');
$slug      = trim($_GET['slug'] ?? '');

// ─── Helper: output asset list as CSV ───
function export_assets_csv(array $assets, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=$filename");
    $out = fopen('php://output', 'w');
    // BOM for Excel compatibility
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'URL', '名称', '是否WP', '状态', '插件数', '主题数', '最后扫描', '创建时间']);
    foreach ($assets as $a) {
        $is_wp_label = $a['is_wp'] === null ? '未知' : ($a['is_wp'] ? '是' : '否');
        fputcsv($out, [
            $a['id'],
            $a['url'],
            $a['name'] ?? '',
            $is_wp_label,
            $a['status'],
            $a['plugin_count'] ?? '',
            $a['theme_count'] ?? '',
            $a['last_scan'] ?? '',
            $a['created_at'] ?? '',
        ]);
    }
    fclose($out);
}

// ─── Helper: output asset list as JSON ───
function export_assets_json(array $assets, string $filename): void
{
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=$filename");
    echo json_encode($assets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// ─── 1. Single asset export (with plugins/themes detail) ───
if ($id !== null) {
    $asset = get_asset($id);
    if (!$asset) {
        http_response_code(404);
        echo json_encode(['error' => 'Asset not found']);
        exit;
    }

    $plugins = get_plugins($id);
    $themes  = get_themes($id);
    $data = ['asset' => $asset, 'plugins' => $plugins, 'themes' => $themes];

    if ($type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=asset_{$id}.csv");
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['类型', 'Slug', '名称', '检测方式', '最后发现']);
        foreach ($plugins as $p) {
            fputcsv($out, ['插件', $p['slug'], $p['name'], $p['detected_via'] ?? '', $p['last_seen'] ?? '']);
        }
        foreach ($themes as $t) {
            fputcsv($out, ['主题', $t['slug'], $t['name'], $t['detected_via'] ?? '', $t['last_seen'] ?? '']);
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=asset_{$id}.json");
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 2. Export all WP assets ───
if ($scope === 'wp') {
    $assets = get_all_wp_assets();

    if ($type === 'csv') {
        export_assets_csv($assets, 'wp_assets.csv');
        exit;
    }

    export_assets_json($assets, 'wp_assets.json');
    exit;
}

// ─── 3. Export non-WP assets ───
if ($scope === 'notwp') {
    $assets = get_all_notwp_assets();

    if ($type === 'csv') {
        export_assets_csv($assets, 'notwp_assets.csv');
        exit;
    }

    export_assets_json($assets, 'notwp_assets.json');
    exit;
}

// ─── 4. Export error/failed assets ───
if ($scope === 'error') {
    $assets = get_all_error_assets();

    if ($type === 'csv') {
        export_assets_csv($assets, 'error_assets.csv');
        exit;
    }

    export_assets_json($assets, 'error_assets.json');
    exit;
}

// ─── 5. Export assets by component (plugin/theme slug) ───
if ($scope === 'component' && $slug !== '') {
    $ct = in_array($comp_type, ['plugin', 'theme']) ? $comp_type : 'plugin';
    $assets = get_all_assets_by_component($ct, $slug);
    $safe_slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);
    $filename = "{$ct}_{$safe_slug}_assets";

    if ($type === 'csv') {
        export_assets_csv($assets, "{$filename}.csv");
        exit;
    }

    export_assets_json($assets, "{$filename}.json");
    exit;
}

// ─── 6. Default: export all assets (with plugins/themes detail) ───
$assets = get_all_assets();
$all_data = [];
foreach ($assets as $a) {
    $all_data[] = [
        'asset'   => $a,
        'plugins' => get_plugins($a['id']),
        'themes'  => get_themes($a['id']),
    ];
}

if ($type === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=all_assets.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['站点URL', '站点名称', '类型', 'Slug', '名称', '检测方式']);
    foreach ($all_data as $d) {
        $a = $d['asset'];
        foreach ($d['plugins'] as $p) {
            fputcsv($out, [$a['url'], $a['name'], '插件', $p['slug'], $p['name'], $p['detected_via'] ?? '']);
        }
        foreach ($d['themes'] as $t) {
            fputcsv($out, [$a['url'], $a['name'], '主题', $t['slug'], $t['name'], $t['detected_via'] ?? '']);
        }
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename=all_assets.json');
echo json_encode($all_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
