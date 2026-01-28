<?php
/**
 * 重新扫描 — 将资产状态重置为 pending，等待后台 worker 重新扫描。
 * 支持：单个、批量勾选、全部失败资产、全部非WP资产。
 */
require_once __DIR__ . '/includes/config.php';
init_db();

$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php';

// POST: 批量 / 按类型
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'batch' && !empty($_POST['ids'])) {
        $ids = (array) $_POST['ids'];
        $count = batch_rescan_assets($ids);
        flash("已将 {$count} 个资产重置为待扫描。", 'success');
    } elseif ($action === 'rescan_error') {
        $count = rescan_error_assets();
        flash("已将 {$count} 个失败资产重置为待扫描。", 'success');
    } elseif ($action === 'rescan_notwp') {
        $count = rescan_notwp_assets();
        flash("已将 {$count} 个非WP资产重置为待扫描。", 'success');
    }

    header('Location: ' . $redirect);
    exit;
}

// GET: 单个
$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    rescan_asset($id);
    flash('已将该资产重置为待扫描。', 'success');
}

header('Location: ' . $redirect);
exit;
