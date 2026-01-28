<?php
/**
 * Add a single WordPress asset.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['url'] ?? '');
    $name = trim($_POST['name'] ?? '') ?: null;
    $auto_scan = isset($_POST['auto_scan']);

    if (!$url) {
        flash('URL is required.', 'danger');
    } else {
        $url = normalize_url($url);
        $asset_id = add_asset($url, $name);

        if ($asset_id === null) {
            flash("Asset '$url' already exists.", 'warning');
            header('Location: index.php');
            exit;
        }

        flash('Asset added successfully.', 'success');

        if ($auto_scan) {
            full_scan($asset_id);
            flash('Scan completed.', 'info');
        }

        header("Location: detail.php?id=$asset_id");
        exit;
    }
}

$page_title = 'Add Asset - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-8">
    <div class="card">
        <div class="card-header"><h4><i class="bi bi-plus-circle"></i> Add WordPress Asset</h4></div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="url" class="form-label">WordPress Site URL <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="url" name="url" placeholder="https://example.com" required>
                    <div class="form-text">Supports <code>http://</code> and <code>https://</code> URLs.</div>
                </div>
                <div class="mb-3">
                    <label for="name" class="form-label">Display Name (optional)</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="My WordPress Site">
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="auto_scan" id="autoScan" checked>
                    <label class="form-check-label" for="autoScan">Auto-scan after adding</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Asset</button>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
