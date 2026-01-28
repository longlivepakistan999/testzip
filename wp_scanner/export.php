<?php
/**
 * Export data as JSON or CSV.
 * ?id=N       → export single asset
 * (no id)     → export all assets
 * ?type=json  → JSON format
 * ?type=csv   → CSV format
 */
require_once __DIR__ . '/includes/config.php';
init_db();

$id   = isset($_GET['id']) ? (int) $_GET['id'] : null;
$type = $_GET['type'] ?? 'json';

// ─── Single asset export ───
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
        fputcsv($out, ['Type', 'Slug', 'Name', 'Detected Via', 'Last Seen']);
        foreach ($plugins as $p) {
            fputcsv($out, ['plugin', $p['slug'], $p['name'], $p['detected_via'] ?? '', $p['last_seen'] ?? '']);
        }
        foreach ($themes as $t) {
            fputcsv($out, ['theme', $t['slug'], $t['name'], $t['detected_via'] ?? '', $t['last_seen'] ?? '']);
        }
        fclose($out);
        exit;
    }

    // JSON
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=asset_{$id}.json");
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── All assets export ───
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
    fputcsv($out, ['Site URL', 'Site Name', 'Type', 'Slug', 'Name', 'Detected Via']);
    foreach ($all_data as $d) {
        $a = $d['asset'];
        foreach ($d['plugins'] as $p) {
            fputcsv($out, [$a['url'], $a['name'], 'plugin', $p['slug'], $p['name'], $p['detected_via'] ?? '']);
        }
        foreach ($d['themes'] as $t) {
            fputcsv($out, [$a['url'], $a['name'], 'theme', $t['slug'], $t['name'], $t['detected_via'] ?? '']);
        }
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename=all_assets.json');
echo json_encode($all_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
