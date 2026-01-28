<?php
/**
 * 某个插件/主题的资产列表 — 独立页面，支持分页、搜索、导出
 *
 * ?type=plugin&slug=xxx  or  ?type=theme&slug=xxx
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$comp_type = trim($_GET['type'] ?? 'plugin');
if (!in_array($comp_type, ['plugin', 'theme'])) $comp_type = 'plugin';

$slug   = trim($_GET['slug'] ?? '');
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));

if ($slug === '') {
    header('Location: components.php');
    exit;
}

$type_label = ($comp_type === 'plugin') ? '插件' : '主题';

// Pagination
$total  = count_assets_by_component($comp_type, $slug, $search);
$pager  = pagination_info($total, $page);
$assets = get_assets_by_component($comp_type, $slug, $pager['page'], $search);

$page_title = "$type_label: $slug - WP Scanner";
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>
        <?php if ($comp_type === 'plugin'): ?>
            <i class="bi bi-puzzle"></i> 插件: <code><?= h($slug) ?></code>
        <?php else: ?>
            <i class="bi bi-palette"></i> 主题: <code><?= h($slug) ?></code>
        <?php endif; ?>
        <span class="badge bg-info"><?= number_format($total) ?> 个资产</span>
    </h2>
    <div class="d-flex gap-2">
        <div class="btn-group">
            <a href="export.php?scope=component&comp_type=<?= h($comp_type) ?>&slug=<?= urlencode($slug) ?>&type=csv"
               class="btn btn-sm btn-outline-success" title="导出 CSV">
                <i class="bi bi-filetype-csv"></i> CSV
            </a>
            <a href="export.php?scope=component&comp_type=<?= h($comp_type) ?>&slug=<?= urlencode($slug) ?>&type=json"
               class="btn btn-sm btn-outline-info" title="导出 JSON">
                <i class="bi bi-filetype-json"></i> JSON
            </a>
        </div>
        <a href="components.php?tab=<?= $comp_type === 'plugin' ? 'plugins' : 'themes' ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> 返回列表
        </a>
    </div>
</div>

<!-- 搜索 -->
<div class="card mb-3">
<div class="card-body py-2">
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="type" value="<?= h($comp_type) ?>">
    <input type="hidden" name="slug" value="<?= h($slug) ?>">
    <div class="col-md-9">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($search) ?>" placeholder="搜索 URL / 名称...">
    </div>
    <div class="col-md-3 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 搜索</button>
        <a href="component_assets.php?type=<?= h($comp_type) ?>&slug=<?= urlencode($slug) ?>"
           class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
    </div>
</form>
</div>
</div>

<!-- 资产列表 -->
<div class="card">
<div class="card-body p-0">
<?php if ($assets): ?>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead>
    <tr>
        <th>ID</th>
        <th>站点</th>
        <th>插件数</th>
        <th>主题数</th>
        <th>状态</th>
        <th>扫描时间</th>
        <th>操作</th>
    </tr>
</thead>
<tbody>
<?php foreach ($assets as $a): ?>
<tr>
    <td><?= $a['id'] ?></td>
    <td>
        <a href="detail.php?id=<?= $a['id'] ?>" class="text-decoration-none">
            <strong><?= h($a['name'] ?: $a['url']) ?></strong>
        </a>
        <br><small class="text-muted"><?= h($a['url']) ?></small>
    </td>
    <td><span class="badge badge-plugin"><?= $a['plugin_count'] ?></span></td>
    <td><span class="badge badge-theme"><?= $a['theme_count'] ?></span></td>
    <td><span class="status-<?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
    <td><small><?= h($a['last_scan'] ?? '-') ?></small></td>
    <td>
        <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="查看"><i class="bi bi-eye"></i></a>
        <a href="export.php?id=<?= $a['id'] ?>&type=json" class="btn btn-sm btn-outline-info" title="导出"><i class="bi bi-download"></i></a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php
    $base_qs = '?type=' . urlencode($comp_type) . '&slug=' . urlencode($slug);
    if ($search !== '') $base_qs .= '&q=' . urlencode($search);
?>
<div class="p-3">
<?= render_pagination($pager, $base_qs) ?>
</div>
<?php else: ?>
<div class="empty-state py-4">
    <i class="bi bi-inbox d-block" style="font-size:2rem;"></i>
    <h5>没有找到使用此<?= $type_label ?>的资产</h5>
    <?php if ($search !== ''): ?>
        <p>当前搜索条件无匹配结果，<a href="component_assets.php?type=<?= h($comp_type) ?>&slug=<?= urlencode($slug) ?>">清除搜索</a></p>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
