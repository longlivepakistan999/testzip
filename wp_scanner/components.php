<?php
/**
 * 插件/主题总览 — 点击查看关联资产列表
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$tab    = trim($_GET['tab'] ?? 'plugins');
if (!in_array($tab, ['plugins', 'themes'])) $tab = 'plugins';

$search = trim($_GET['q'] ?? '');
$slug   = trim($_GET['slug'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));

// 如果选定了某个 slug，展示对应资产列表
$view_assets = false;
$comp_type = ($tab === 'themes') ? 'theme' : 'plugin';
$comp_assets = [];
$comp_pager = null;

if ($slug !== '') {
    $view_assets = true;
    $comp_total = count_assets_by_component($comp_type, $slug);
    $comp_pager = pagination_info($comp_total, $page);
    $comp_assets = get_assets_by_component($comp_type, $slug, $comp_pager['page']);
}

// 总览列表
$all_plugins = get_all_plugins_summary($search);
$all_themes  = get_all_themes_summary($search);

$page_title = '插件与主题 - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-collection"></i> 插件与主题</h2>
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

<?php if ($view_assets): ?>
<!-- 某个插件/主题的关联资产列表 -->
<div class="card mb-3">
<div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
        <?php if ($comp_type === 'plugin'): ?>
            <i class="bi bi-puzzle"></i> 插件: <code><?= h($slug) ?></code>
        <?php else: ?>
            <i class="bi bi-palette"></i> 主题: <code><?= h($slug) ?></code>
        <?php endif; ?>
        <span class="badge bg-info"><?= number_format($comp_pager['total']) ?> 个资产</span>
    </h5>
    <a href="components.php?tab=<?= h($tab) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
       class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> 返回列表</a>
</div>
<div class="card-body p-0">
<?php if ($comp_assets): ?>
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
<?php foreach ($comp_assets as $a): ?>
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
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php
    $comp_base_qs = '?tab=' . urlencode($tab) . '&slug=' . urlencode($slug);
    if ($search !== '') $comp_base_qs .= '&q=' . urlencode($search);
?>
<div class="p-3">
<?= render_pagination($comp_pager, $comp_base_qs) ?>
</div>
<?php else: ?>
<div class="empty-state py-3">
    <p>没有找到使用此<?= $comp_type === 'plugin' ? '插件' : '主题' ?>的资产。</p>
</div>
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<!-- 插件/主题总览列表 -->
<div class="card">
<div class="card-body p-0">
<?php
$items = ($tab === 'plugins') ? $all_plugins : $all_themes;
$type_label = ($tab === 'plugins') ? '插件' : '主题';
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
        <th>操作</th>
    </tr>
</thead>
<tbody>
<?php foreach ($items as $i => $item): ?>
<tr class="<?= ($slug === $item['slug']) ? 'table-active' : '' ?>">
    <td><?= $i + 1 ?></td>
    <td><code><?= h($item['slug']) ?></code></td>
    <td><?= h($item['name'] ?: $item['slug']) ?></td>
    <td><span class="badge bg-info"><?= number_format($item['asset_count']) ?></span></td>
    <td>
        <a href="components.php?tab=<?= h($tab) ?>&slug=<?= urlencode($item['slug']) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
           class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye"></i> 查看资产
        </a>
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
