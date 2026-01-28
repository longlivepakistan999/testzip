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
        flash('未提供有效的 URL。', 'warning');
    } else {
        $result = batch_add_assets($normalized);
        $msg = "导入完成：新增 {$result['added']} 个，跳过重复 {$result['skipped']} 个。";
        $msg .= " 待扫描资产将由后台任务自动处理。";
        flash($msg, 'success');
    }

    header('Location: index.php');
    exit;
}

$page_title = '导入URL - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-8">
    <div class="card">
        <div class="card-header"><h4><i class="bi bi-upload"></i> 导入 URL 列表</h4></div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="urls" class="form-label">URL 列表 <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="urls" name="urls" rows="12"
                              placeholder="https://example.com
http://another-site.com
https://wp-blog.org
http://my-wordpress.net" required></textarea>
                    <div class="form-text">
                        每行一个 URL，支持 <code>http://</code> 和 <code>https://</code>。
                        未填写协议前缀的将默认使用 <code>https://</code>。
                    </div>
                </div>
                <div class="alert alert-info py-2">
                    <i class="bi bi-info-circle"></i>
                    导入的 URL 将保存为<strong>待扫描</strong>状态。
                    运行 <code>php cron_scan.php</code> 或配置定时任务以在后台执行扫描。
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> 导入</button>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> 取消</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><h5><i class="bi bi-terminal"></i> 后台扫描命令</h5></div>
        <div class="card-body">
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td><code>php cron_scan.php</code></td><td>扫描最多 100 个待扫描资产</td></tr>
                    <tr><td><code>php cron_scan.php 500</code></td><td>扫描最多 500 个待扫描资产</td></tr>
                    <tr><td><code>php cron_scan.php --loop</code></td><td>持续运行直到全部完成</td></tr>
                    <tr><td><code>php cron_scan.php --loop 200</code></td><td>持续运行，每批 200 个</td></tr>
                </tbody>
            </table>
            <div class="mt-2">
                <small class="text-muted">定时任务示例：<code>* * * * * php /path/to/cron_scan.php 200 >> /var/log/wp_scan.log 2>&1</code></small>
            </div>
        </div>
    </div>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
