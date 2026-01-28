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

$page_title = 'WP Scanner - Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
    <div>
        <a href="import.php" class="btn btn-outline-primary me-2"><i class="bi bi-upload"></i> Import</a>
        <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add</a>
    </div>
</div>

<!-- Status Summary -->
<div class="row mb-3">
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">Total</small>
        <strong><?= number_format($total) ?></strong>
    </div></div>
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">Pending</small>
        <strong class="text-warning"><?= number_format($stats['pending'] ?? 0) ?></strong>
    </div></div>
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">Scanned</small>
        <strong class="text-success"><?= number_format($stats['scanned'] ?? 0) ?></strong>
    </div></div>
    <div class="col-md-3"><div class="card text-center p-2">
        <small class="text-muted">Error</small>
        <strong class="text-danger"><?= number_format($stats['error'] ?? 0) ?></strong>
    </div></div>
</div>

<!-- Search + Filter -->
<div class="card mb-3">
<div class="card-body py-2">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($search) ?>" placeholder="URL / name...">
    </div>
    <div class="col-md-2">
        <input type="text" class="form-control form-control-sm" name="plugin"
               value="<?= h($plugin) ?>" placeholder="Plugin slug...">
    </div>
    <div class="col-md-2">
        <input type="text" class="form-control form-control-sm" name="theme"
               value="<?= h($theme) ?>" placeholder="Theme slug...">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="scanned" <?= $status === 'scanned' ? 'selected' : '' ?>>Scanned</option>
            <option value="scanning" <?= $status === 'scanning' ? 'selected' : '' ?>>Scanning</option>
            <option value="not_wp" <?= $status === 'not_wp' ? 'selected' : '' ?>>Not WP</option>
            <option value="error" <?= $status === 'error' ? 'selected' : '' ?>>Error</option>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> Search</button>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
    </div>
</form>
</div>
</div>

<!-- Asset Table -->
<div class="card">
<div class="card-body p-0">
<?php if ($assets): ?>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead>
    <tr>
        <th>ID</th>
        <th>Site</th>
        <th>WP</th>
        <th>Plugins</th>
        <th>Themes</th>
        <th>Status</th>
        <th>Last Scan</th>
        <th>Actions</th>
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
    <td>
        <?php if ($a['is_wp'] === null): ?><span class="text-muted">-</span>
        <?php elseif ($a['is_wp']): ?><span class="badge bg-success">Yes</span>
        <?php else: ?><span class="badge bg-secondary">No</span>
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
            <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
            <a href="scan.php?id=<?= $a['id'] ?>" class="btn btn-outline-success" title="Scan"><i class="bi bi-search"></i></a>
            <a href="export.php?id=<?= $a['id'] ?>&type=json" class="btn btn-outline-secondary" title="Export"><i class="bi bi-download"></i></a>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<div class="p-3">
<?= render_pagination($pager, $base_qs) ?>
</div>

<?php else: ?>
<div class="empty-state">
    <i class="bi bi-globe2 d-block"></i>
    <h4>No assets found</h4>
    <?php if ($search || $status): ?>
        <p>No results matching your search. <a href="index.php">Clear filters</a></p>
    <?php else: ?>
        <p>Add WordPress sites or import a URL list to start.</p>
        <a href="add.php" class="btn btn-primary me-2"><i class="bi bi-plus-circle"></i> Add</a>
        <a href="import.php" class="btn btn-outline-primary"><i class="bi bi-upload"></i> Import</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
