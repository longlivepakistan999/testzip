<?php
/**
 * 非WordPress资产列表
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));

$total  = count_notwp_assets($search);
$pager  = pagination_info($total, $page);
$assets = get_notwp_assets_page($pager['page'], $search);

$qs_parts = [];
if ($search !== '') $qs_parts[] = 'q=' . urlencode($search);
$base_qs = $qs_parts ? '?' . implode('&', $qs_parts) : '';

$page_title = '非WP资产 - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-x-circle"></i> 非WordPress资产</h2>
    <span class="badge bg-secondary fs-6"><?= number_format($total) ?> 个</span>
</div>

<!-- 搜索 -->
<div class="card mb-3">
<div class="card-body py-2">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-9">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($search) ?>" placeholder="搜索 URL / 名称...">
    </div>
    <div class="col-md-3 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 搜索</button>
        <a href="notwp.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
    </div>
</form>
</div>
</div>

<!-- 资产列表 -->
<div class="card">
<div class="card-body p-0">
<?php if ($assets): ?>
<form id="batchForm" method="POST" action="delete.php">
<input type="hidden" name="action" value="batch">
<input type="hidden" name="redirect" value="notwp.php<?= h($base_qs) ?>">

<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
    <div class="d-flex align-items-center gap-2">
        <span id="selectedCount" class="text-muted small">已选 0 项</span>
        <button type="submit" class="btn btn-sm btn-outline-danger" id="batchDeleteBtn" disabled
                onclick="return confirm('确定删除选中的资产及其所有扫描数据？');">
            <i class="bi bi-trash"></i> 删除选中
        </button>
    </div>
    <form method="POST" action="delete.php" class="d-inline">
        <input type="hidden" name="action" value="clear_notwp">
        <input type="hidden" name="redirect" value="notwp.php">
        <button type="submit" class="btn btn-sm btn-danger"
                onclick="return confirm('确定清除全部非WP资产？此操作不可恢复！');">
            <i class="bi bi-trash-fill"></i> 清除全部非WP资产
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
        <span class="status-<?= h($a['status']) ?>">
            <?= h($a['status']) ?>
        </span>
    </td>
    <td><small><?= h($a['last_scan'] ?? '-') ?></small></td>
    <td>
        <div class="btn-group btn-group-sm">
            <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-outline-primary" title="查看"><i class="bi bi-eye"></i></a>
            <a href="scan.php?id=<?= $a['id'] ?>" class="btn btn-outline-success" title="重新扫描"><i class="bi bi-search"></i></a>
            <a href="delete.php?id=<?= $a['id'] ?>&redirect=notwp.php" class="btn btn-outline-danger" title="删除"
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

<div class="p-3">
<?= render_pagination($pager, $base_qs) ?>
</div>

<?php else: ?>
<div class="empty-state">
    <i class="bi bi-x-circle d-block"></i>
    <h4>暂无非WordPress资产</h4>
    <?php if ($search): ?>
        <p>没有匹配的结果。<a href="notwp.php">清除搜索</a></p>
    <?php else: ?>
        <p>扫描完成后，非WordPress站点将显示在此处。</p>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
