<?php
/**
 * Trigger scan for an asset.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$id = (int) ($_GET['id'] ?? 0);
$asset = get_asset($id);

if (!$asset) {
    flash('Asset not found.', 'danger');
    header('Location: index.php');
    exit;
}

$results = full_scan($id);

if (!empty($results['errors'])) {
    flash('Scan failed: ' . implode(', ', $results['errors']), 'danger');
} else {
    $pc = count($results['plugins']);
    $tc = count($results['themes']);
    flash("Scan complete: found $pc plugins, $tc themes.", 'success');
}

header("Location: detail.php?id=$id");
exit;
