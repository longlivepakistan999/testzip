<?php
/**
 * Dashboard - list all assets.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$assets = get_all_assets();
$page_title = 'WP Scanner - Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2"></i> Asset Dashboard</h2>
    <div>
        <a href="import.php" class="btn btn-outline-primary me-2"><i class="bi bi-upload"></i> Import URLs</a>
        <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Asset</a>
    </div>
</div>

<div class="card">
<div class="card-body">
<?php if ($assets): ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
    <tr>
        <th>#</th>
        <th>Site</th>
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
    <td><span class="badge badge-plugin"><?= $a['plugin_count'] ?></span></td>
    <td><span class="badge badge-theme"><?= $a['theme_count'] ?></span></td>
    <td>
        <span class="status-<?= h($a['status']) ?>">
            <?php if ($a['status'] === 'scanned'): ?><i class="bi bi-check-circle-fill"></i>
            <?php elseif ($a['status'] === 'error'): ?><i class="bi bi-x-circle-fill"></i>
            <?php else: ?><i class="bi bi-clock"></i>
            <?php endif; ?>
            <?= h($a['status']) ?>
        </span>
    </td>
    <td><small><?= h($a['last_scan'] ?: '-') ?></small></td>
    <td>
        <div class="btn-group btn-group-sm">
            <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
            <a href="scan.php?id=<?= $a['id'] ?>" class="btn btn-outline-success" title="Scan"><i class="bi bi-search"></i></a>
            <a href="export.php?id=<?= $a['id'] ?>&type=json" class="btn btn-outline-secondary" title="Export JSON"><i class="bi bi-download"></i></a>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="bi bi-globe2 d-block"></i>
    <h4>No assets yet</h4>
    <p>Add WordPress sites or import a URL list to start scanning.</p>
    <a href="add.php" class="btn btn-primary me-2"><i class="bi bi-plus-circle"></i> Add Asset</a>
    <a href="import.php" class="btn btn-outline-primary"><i class="bi bi-upload"></i> Import URLs</a>
</div>
<?php endif; ?>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
