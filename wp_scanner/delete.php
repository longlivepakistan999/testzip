<?php
/**
 * 删除资产 — 支持单个删除、批量勾选删除、按状态一键清除。
 * 所有关联的插件、主题、扫描日志通过外键级联自动删除。
 */
require_once __DIR__ . '/includes/config.php';
init_db();

$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php';

// POST: 批量删除 / 按状态清除
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'batch' && !empty($_POST['ids'])) {
        $ids = (array) $_POST['ids'];
        $count = batch_delete_assets($ids);
        flash("已删除 {$count} 个资产及其所有扫描数据。", 'success');
    } elseif ($action === 'clear_error') {
        $count = delete_assets_by_status('error');
        flash("已清除 {$count} 个失败资产及其所有扫描数据。", 'success');
    } elseif ($action === 'clear_notwp') {
        $count = delete_notwp_assets();
        flash("已清除 {$count} 个非WP资产及其所有扫描数据。", 'success');
    } elseif ($action === 'clear_all') {
        $count = delete_all_assets();
        flash("已清空全部 {$count} 个资产及所有扫描数据。", 'success');
    }

    header('Location: ' . $redirect);
    exit;
}

// GET: 单个删除
$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    delete_asset($id);
    flash('已删除该资产及其所有扫描数据。', 'success');
}

header('Location: ' . $redirect);
exit;
