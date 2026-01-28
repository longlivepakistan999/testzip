<?php
/**
 * Delete an asset.
 */
require_once __DIR__ . '/includes/config.php';
init_db();

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    delete_asset($id);
    flash('Asset deleted.', 'success');
}

header('Location: index.php');
exit;
