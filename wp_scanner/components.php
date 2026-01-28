<?php
/**
 * 插件/主题总览 — 查看关联资产跳转独立页面
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$tab    = trim($_GET['tab'] ?? 'plugins');
if (!in_array($tab, ['plugins', 'themes'])) $tab = 'plugins';

$search = trim($_GET['q'] ?? '');

// 总览列表
$all_plugins = get_all_plugins_summary($search);
$all_themes  = get_all_themes_summary($search);
$total_wp    = count_wp_assets();

$page_title = '插件与主题 - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-collection"></i> 插件与主题</h2>
    <div class="d-flex gap-2">
        <div class="btn-group">
            <a href="export.php?scope=wp&type=csv" class="btn btn-sm btn-outline-success" title="导出所有WP资产 CSV">
                <i class="bi bi-filetype-csv"></i> 导出WP资产
            </a>
            <a href="export.php?scope=wp&type=json" class="btn btn-sm btn-outline-info" title="导出所有WP资产 JSON">
                <i class="bi bi-filetype-json"></i> JSON
            </a>
        </div>
    </div>
</div>

<!-- Tab 切换 -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'plugins' ? 'active' : '' ?>"
           href="components.php?tab=plugins<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
            <i class="bi bi-puzzle"></i> 插件
            <span class="badge badge-plugin"><?= count($all_plugins) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'themes' ? 'active' : '' ?>"
           href="components.php?tab=themes<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
            <i class="bi bi-palette"></i> 主题
            <span class="badge badge-theme"><?= count($all_themes) ?></span>
        </a>
    </li>
</ul>

<!-- 搜索 -->
<div class="card mb-3">
<div class="card-body py-2">
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div class="col-md-9">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($search) ?>" placeholder="搜索 slug / 名称...">
    </div>
    <div class="col-md-3 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 搜索</button>
        <a href="components.php?tab=<?= h($tab) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
    </div>
</form>
</div>
</div>

<!-- 统计摘要 -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body py-2">
            <small class="text-muted">WP 资产总数</small>
            <h4 class="mb-0 text-primary"><?= number_format($total_wp) ?></h4>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body py-2">
            <small class="text-muted">插件种类</small>
            <h4 class="mb-0" style="color:#0d6efd;"><?= number_format(count($all_plugins)) ?></h4>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body py-2">
            <small class="text-muted">主题种类</small>
            <h4 class="mb-0" style="color:#6f42c1;"><?= number_format(count($all_themes)) ?></h4>
        </div></div>
    </div>
</div>

<!-- 插件/主题总览列表 -->
<div class="card">
<div class="card-body p-0">
<?php
$items = ($tab === 'plugins') ? $all_plugins : $all_themes;
$type_label = ($tab === 'plugins') ? '插件' : '主题';
$comp_type_val = ($tab === 'plugins') ? 'plugin' : 'theme';
?>
<?php if ($items): ?>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead>
    <tr>
        <th>#</th>
        <th>Slug</th>
        <th>名称</th>
        <th>资产数量</th>
        <th>占比</th>
        <th>操作</th>
    </tr>
</thead>
<tbody>
<?php foreach ($items as $i => $item):
    $pct = $total_wp > 0 ? round($item['asset_count'] / $total_wp * 100, 1) : 0;
    $bar_color = $pct >= 50 ? 'bg-danger' : ($pct >= 20 ? 'bg-warning' : ($pct >= 5 ? 'bg-info' : 'bg-success'));
?>
<tr>
    <td><?= $i + 1 ?></td>
    <td><code><?= h($item['slug']) ?></code></td>
    <td><?= h($item['name'] ?: $item['slug']) ?></td>
    <td><span class="badge bg-info"><?= number_format($item['asset_count']) ?></span></td>
    <td style="min-width:160px;">
        <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:18px;">
                <div class="progress-bar <?= $bar_color ?>" style="width:<?= max($pct, 1) ?>%"></div>
            </div>
            <small class="text-nowrap"><strong><?= $pct ?>%</strong></small>
        </div>
    </td>
    <td class="text-nowrap">
        <a href="component_assets.php?type=<?= h($comp_type_val) ?>&slug=<?= urlencode($item['slug']) ?>"
           class="btn btn-sm btn-outline-primary" title="查看资产">
            <i class="bi bi-eye"></i> 查看
        </a>
        <div class="btn-group ms-1">
            <a href="export.php?scope=component&comp_type=<?= h($comp_type_val) ?>&slug=<?= urlencode($item['slug']) ?>&type=csv"
               class="btn btn-sm btn-outline-success" title="导出 CSV">
                <i class="bi bi-filetype-csv"></i>
            </a>
            <a href="export.php?scope=component&comp_type=<?= h($comp_type_val) ?>&slug=<?= urlencode($item['slug']) ?>&type=json"
               class="btn btn-sm btn-outline-info" title="导出 JSON">
                <i class="bi bi-filetype-json"></i>
            </a>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="bi bi-collection d-block"></i>
    <h4>暂无<?= $type_label ?>数据</h4>
    <p>扫描资产后，检测到的<?= $type_label ?>将显示在此处。</p>
</div>
<?php endif; ?>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
