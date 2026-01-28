<?php
/**
 * Asset detail - plugins, themes, scan logs, export.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$id = (int) ($_GET['id'] ?? 0);
$asset = get_asset($id);
if (!$asset) {
    flash('Asset not found.', 'danger');
    header('Location: index.php');
    exit;
}

$plugins = get_plugins($id);
$themes  = get_themes($id);
$logs    = get_scan_logs($id);

$page_title = ($asset['name'] ?: $asset['url']) . ' - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h2><i class="bi bi-globe2"></i> <?= h($asset['name'] ?: $asset['url']) ?></h2>
        <p class="text-muted mb-0">
            <a href="<?= h($asset['url']) ?>" target="_blank" class="text-decoration-none">
                <?= h($asset['url']) ?> <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="scan.php?id=<?= $id ?>" class="btn btn-scan"><i class="bi bi-search"></i> Re-scan</a>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download"></i> Export
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?id=<?= $id ?>&type=json"><i class="bi bi-filetype-json"></i> JSON</a></li>
                <li><a class="dropdown-item" href="export.php?id=<?= $id ?>&type=csv"><i class="bi bi-filetype-csv"></i> CSV</a></li>
            </ul>
        </div>
        <a href="delete.php?id=<?= $id ?>" class="btn btn-outline-danger"
           onclick="return confirm('Are you sure?');"><i class="bi bi-trash"></i> Delete</a>
    </div>
</div>

<!-- Summary -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <h6 class="text-muted">Plugins</h6>
            <h3 class="text-primary"><?= count($plugins) ?></h3>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <h6 class="text-muted">Themes</h6>
            <h3 style="color:#6f42c1;"><?= count($themes) ?></h3>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <h6 class="text-muted">Status</h6>
            <h3 class="status-<?= h($asset['status']) ?>"><?= h($asset['status']) ?></h3>
        </div></div>
    </div>
</div>

<!-- Plugins -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-puzzle"></i> Plugins <span class="badge badge-plugin"><?= count($plugins) ?></span></h5>
    </div>
    <div class="card-body">
    <?php if ($plugins): ?>
        <div class="table-responsive">
        <table class="table table-hover table-sm">
        <thead><tr><th>#</th><th>Slug</th><th>Name</th><th>Detected Via</th><th>Last Seen</th></tr></thead>
        <tbody>
        <?php foreach ($plugins as $i => $p): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><code><?= h($p['slug']) ?></code></td>
            <td><?= h($p['name'] ?: $p['slug']) ?></td>
            <td><small class="text-muted"><?= h($p['detected_via'] ?: '-') ?></small></td>
            <td><small><?= h($p['last_seen'] ?: '-') ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
    <?php else: ?>
        <div class="empty-state py-3">
            <i class="bi bi-puzzle d-block" style="font-size:2rem;"></i>
            <p>No plugins detected. Run a scan to discover plugins.</p>
        </div>
    <?php endif; ?>
    </div>
</div>

<!-- Themes -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-palette"></i> Themes <span class="badge badge-theme"><?= count($themes) ?></span></h5>
    </div>
    <div class="card-body">
    <?php if ($themes): ?>
        <div class="table-responsive">
        <table class="table table-hover table-sm">
        <thead><tr><th>#</th><th>Slug</th><th>Name</th><th>Active</th><th>Detected Via</th></tr></thead>
        <tbody>
        <?php foreach ($themes as $i => $t): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><code><?= h($t['slug']) ?></code></td>
            <td><?= h($t['name'] ?: $t['slug']) ?></td>
            <td><?= $t['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="text-muted">-</span>' ?></td>
            <td><small class="text-muted"><?= h($t['detected_via'] ?: '-') ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
    <?php else: ?>
        <div class="empty-state py-3">
            <i class="bi bi-palette d-block" style="font-size:2rem;"></i>
            <p>No themes detected. Run a scan to discover themes.</p>
        </div>
    <?php endif; ?>
    </div>
</div>

<!-- Scan Logs -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-text"></i> Scan History</h5></div>
    <div class="card-body">
    <?php if ($logs): ?>
        <table class="table table-sm">
        <thead><tr><th>Time</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
            <td><small><?= h($l['scan_time']) ?></small></td>
            <td><span class="badge <?= $l['status'] === 'success' ? 'bg-success' : ($l['status'] === 'error' ? 'bg-danger' : 'bg-secondary') ?>"><?= h($l['status']) ?></span></td>
            <td><?= h($l['message']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted mb-0">No scan history.</p>
    <?php endif; ?>
    </div>
</div>

<a href="index.php" class="btn btn-secondary mb-4"><i class="bi bi-arrow-left"></i> Back</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
