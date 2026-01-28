<?php
/**
 * Import URL list - batch add WordPress assets.
 * Supports URLs with http:// and https:// prefixes.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urls_text = $_POST['urls'] ?? '';
    $auto_scan = isset($_POST['auto_scan']);
    $added = 0;
    $skipped = 0;
    $scanned = 0;

    $lines = preg_split('/\r?\n/', trim($urls_text));
    foreach ($lines as $line) {
        $url = trim($line);
        if ($url === '') continue;

        $url = normalize_url($url);
        $asset_id = add_asset($url);

        if ($asset_id === null) {
            $skipped++;
            continue;
        }

        $added++;

        if ($auto_scan) {
            full_scan($asset_id);
            $scanned++;
        }
    }

    $msg = "Imported: $added added, $skipped duplicates skipped.";
    if ($auto_scan) {
        $msg .= " Scanned $scanned sites.";
    }
    flash($msg, 'success');
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
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="auto_scan" id="autoScan" checked>
                    <label class="form-check-label" for="autoScan">Auto-scan after importing</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import</button>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><h5><i class="bi bi-info-circle"></i> Detection Methods</h5></div>
        <div class="card-body">
            <table class="table table-sm mb-0">
                <thead><tr><th>Method</th><th>Detects</th><th>Details</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>/wp-json/</code></td>
                        <td>Plugins</td>
                        <td>Identifies plugins via REST API namespaces (e.g., <code>yoast/v1</code>, <code>wc/v3</code>)</td>
                    </tr>
                    <tr>
                        <td>HTML Source</td>
                        <td>Plugins + Themes</td>
                        <td>Parses <code>/wp-content/plugins/</code> and <code>/wp-content/themes/</code> references</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
