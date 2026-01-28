<?php
/**
 * Dashboard — paginated asset list with search and filter.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$plugin = trim($_GET['plugin'] ?? '');
$theme  = trim($_GET['theme'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));

$total  = count_assets($search, $status, $plugin, $theme);
$pager  = pagination_info($total, $page);
$assets = get_assets_page($pager['page'], $search, $status, $plugin, $theme);
$stats  = count_assets_by_status();

// Build base query string for pagination links
$qs_parts = [];
if ($search !== '') $qs_parts[] = 'q=' . urlencode($search);
if ($status !== '') $qs_parts[] = 'status=' . urlencode($status);
if ($plugin !== '') $qs_parts[] = 'plugin=' . urlencode($plugin);
if ($theme !== '')  $qs_parts[] = 'theme=' . urlencode($theme);
$base_qs = $qs_parts ? '?' . implode('&', $qs_parts) : '';

$page_title = 'WP Scanner - 仪表盘';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-speedometer2"></i> 仪表盘</h2>
    <div>
        <a href="import.php" class="btn btn-outline-primary me-2"><i class="bi bi-upload"></i> 导入</a>
        <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> 添加</a>
    </div>
</div>

<!-- Status Summary -->
<div class="row mb-3">
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">总计</small>
        <strong><?= number_format($total) ?></strong>
    </div></div>
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">待扫描</small>
        <strong class="text-warning"><?= number_format($stats['pending'] ?? 0) ?></strong>
    </div></div>
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">已扫描</small>
        <strong class="text-success"><?= number_format($stats['scanned'] ?? 0) ?></strong>
    </div></div>
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">错误</small>
        <strong class="text-danger"><?= number_format($stats['error'] ?? 0) ?></strong>
    </div></div>
</div>

<!-- Search + Filter -->
<div class="card mb-3">
<div class="card-body py-2">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($search) ?>" placeholder="URL / 名称...">
    </div>
    <div class="col-md-2">
        <input type="text" class="form-control form-control-sm" name="plugin"
               value="<?= h($plugin) ?>" placeholder="插件 slug...">
    </div>
    <div class="col-md-2">
        <input type="text" class="form-control form-control-sm" name="theme"
               value="<?= h($theme) ?>" placeholder="主题 slug...">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">全部状态</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>待扫描</option>
            <option value="scanned" <?= $status === 'scanned' ? 'selected' : '' ?>>已扫描</option>
            <option value="scanning" <?= $status === 'scanning' ? 'selected' : '' ?>>扫描中</option>
            <option value="not_wp" <?= $status === 'not_wp' ? 'selected' : '' ?>>非WP</option>
            <option value="error" <?= $status === 'error' ? 'selected' : '' ?>>错误</option>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 搜索</button>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
    </div>
</form>
</div>
</div>

<!-- Asset Table -->
<div class="card">
<div class="card-body p-0">
<?php if ($assets): ?>
<form id="batchForm" method="POST" action="delete.php">
<input type="hidden" name="action" value="batch">
<input type="hidden" name="redirect" value="index.php<?= h($base_qs) ?>">

<!-- 批量操作栏 -->
<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
    <div class="d-flex align-items-center gap-2">
        <span id="selectedCount" class="text-muted small">已选 0 项</span>
        <button type="submit" class="btn btn-sm btn-outline-danger" id="batchDeleteBtn" disabled
                onclick="return confirm('确定删除选中的资产及其所有扫描数据？');">
            <i class="bi bi-trash"></i> 删除选中
        </button>
    </div>
    <form method="POST" action="delete.php" class="d-inline">
        <input type="hidden" name="action" value="clear_all">
        <input type="hidden" name="redirect" value="index.php">
        <button type="submit" class="btn btn-sm btn-danger"
                onclick="return confirm('确定清空全部资产？此操作不可恢复！所有资产、插件、主题、扫描日志将全部删除。');">
            <i class="bi bi-trash-fill"></i> 清空所有资产
        </button>
    </form>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead>
    <tr>
        <th style="width:40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
        <th>ID</th>
        <th>站点</th>
        <th>WP</th>
        <th>插件</th>
        <th>主题</th>
        <th>状态</th>
        <th>扫描时间</th>
        <th>操作</th>
    </tr>
</thead>
<tbody>
<?php foreach ($assets as $a): ?>
<tr>
    <td><input type="checkbox" name="ids[]" value="<?= $a['id'] ?>" class="form-check-input row-check"></td>
    <td><?= $a['id'] ?></td>
    <td>
        <a href="detail.php?id=<?= $a['id'] ?>" class="text-decoration-none">
            <strong><?= h($a['name'] ?: $a['url']) ?></strong>
        </a>
        <br><small class="text-muted"><?= h($a['url']) ?></small>
    </td>
    <td>
        <?php if ($a['is_wp'] === null): ?><span class="text-muted">-</span>
        <?php elseif ($a['is_wp']): ?><span class="badge bg-success">是</span>
        <?php else: ?><span class="badge bg-secondary">否</span>
        <?php endif; ?>
    </td>
    <td><span class="badge badge-plugin"><?= $a['plugin_count'] ?></span></td>
    <td><span class="badge badge-theme"><?= $a['theme_count'] ?></span></td>
    <td>
        <span class="status-<?= h($a['status']) ?>">
            <?php if ($a['status'] === 'scanned'): ?><i class="bi bi-check-circle-fill"></i>
            <?php elseif ($a['status'] === 'error'): ?><i class="bi bi-x-circle-fill"></i>
            <?php elseif ($a['status'] === 'scanning'): ?><i class="bi bi-arrow-repeat"></i>
            <?php else: ?><i class="bi bi-clock"></i>
            <?php endif; ?>
            <?= h($a['status']) ?>
        </span>
    </td>
    <td><small><?= h($a['last_scan'] ?? '-') ?></small></td>
    <td>
        <div class="btn-group btn-group-sm">
            <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-outline-primary" title="查看"><i class="bi bi-eye"></i></a>
            <a href="scan.php?id=<?= $a['id'] ?>" class="btn btn-outline-success" title="扫描"><i class="bi bi-search"></i></a>
            <a href="delete.php?id=<?= $a['id'] ?>&redirect=index.php<?= urlencode($base_qs) ?>" class="btn btn-outline-danger" title="删除"
               onclick="return confirm('确定删除？');"><i class="bi bi-trash"></i></a>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</form>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
    updateCount();
});
document.querySelectorAll('.row-check').forEach(c => c.addEventListener('change', updateCount));
function updateCount() {
    var n = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = '已选 ' + n + ' 项';
    document.getElementById('batchDeleteBtn').disabled = n === 0;
}
</script>

<!-- Pagination -->
<div class="p-3">
<?= render_pagination($pager, $base_qs) ?>
</div>

<?php else: ?>
<div class="empty-state">
    <i class="bi bi-globe2 d-block"></i>
    <h4>暂无资产</h4>
    <?php if ($search || $status): ?>
        <p>没有匹配的结果。<a href="index.php">清除筛选</a></p>
    <?php else: ?>
        <p>添加 WordPress 站点或导入 URL 列表开始使用。</p>
        <a href="add.php" class="btn btn-primary me-2"><i class="bi bi-plus-circle"></i> 添加</a>
        <a href="import.php" class="btn btn-outline-primary"><i class="bi bi-upload"></i> 导入</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
