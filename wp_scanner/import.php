<?php
/**
 * Import URL list — batch add WordPress assets.
 * Only inserts into DB. Scanning is handled by cron_scan.php.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urls_text = $_POST['urls'] ?? '';

    $lines = preg_split('/\r?\n/', trim($urls_text));
    $normalized = [];
    foreach ($lines as $line) {
        $url = trim($line);
        if ($url === '') continue;
        $normalized[] = normalize_url($url);
    }

    if (empty($normalized)) {
        flash('No valid URLs provided.', 'warning');
    } else {
        $result = batch_add_assets($normalized);
        $msg = "Imported: {$result['added']} added, {$result['skipped']} duplicates skipped.";
        $msg .= " Pending assets will be scanned by the background worker.";
        flash($msg, 'success');
    }

    header('Location: index.php');
    exit;
}

$page_title = 'Import URLs - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-8">
    <div class="card">
        <div class="card-header"><h4><i class="bi bi-upload"></i> Import URL List</h4></div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="urls" class="form-label">URL List <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="urls" name="urls" rows="12"
                              placeholder="https://example.com
http://another-site.com
https://wp-blog.org
http://my-wordpress.net" required></textarea>
                    <div class="form-text">
                        One URL per line. Supports <code>http://</code> and <code>https://</code>.
                        URLs without protocol prefix will default to <code>https://</code>.
                    </div>
                </div>
                <div class="alert alert-info py-2">
                    <i class="bi bi-info-circle"></i>
                    Imported URLs are saved as <strong>pending</strong>.
                    Run <code>php cron_scan.php</code> or set up a cron job to scan them in the background.
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import</button>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><h5><i class="bi bi-terminal"></i> Background Scan Commands</h5></div>
        <div class="card-body">
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td><code>php cron_scan.php</code></td><td>Scan up to 100 pending assets</td></tr>
                    <tr><td><code>php cron_scan.php 500</code></td><td>Scan up to 500 pending assets</td></tr>
                    <tr><td><code>php cron_scan.php --loop</code></td><td>Run continuously until all scanned</td></tr>
                    <tr><td><code>php cron_scan.php --loop 200</code></td><td>Continuous, 200 per batch</td></tr>
                </tbody>
            </table>
            <div class="mt-2">
                <small class="text-muted">Cron example: <code>* * * * * php /path/to/cron_scan.php 200 >> /var/log/wp_scan.log 2>&1</code></small>
            </div>
        </div>
    </div>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
