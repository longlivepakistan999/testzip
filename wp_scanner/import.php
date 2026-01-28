<?php
/**
 * 导入 URL — 支持文本粘贴和 CSV/TXT 文件上传。
 * 只插入数据库，扫描由 cron_scan.php 处理。
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $normalized = [];

    // 1. 文件上传
    if (!empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $content = file_get_contents($_FILES['file']['tmp_name']);

        if ($ext === 'csv') {
            // CSV: 取每行第一列作为 URL
            $rows = array_map('str_getcsv', explode("\n", $content));
            foreach ($rows as $row) {
                $val = trim($row[0] ?? '');
                if ($val === '' || strtolower($val) === 'url') continue; // 跳过表头
                $normalized[] = normalize_url($val);
            }
        } else {
            // TXT: 每行一个 URL
            $lines = preg_split('/\r?\n/', trim($content));
            foreach ($lines as $line) {
                $url = trim($line);
                if ($url === '') continue;
                $normalized[] = normalize_url($url);
            }
        }
    }

    // 2. 文本框粘贴
    $urls_text = trim($_POST['urls'] ?? '');
    if ($urls_text !== '') {
        $lines = preg_split('/\r?\n/', $urls_text);
        foreach ($lines as $line) {
            $url = trim($line);
            if ($url === '') continue;
            $normalized[] = normalize_url($url);
        }
    }

    // 去重
    $normalized = array_unique($normalized);

    if (empty($normalized)) {
        flash('未提供有效的 URL，请粘贴文本或上传文件。', 'warning');
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
        <div class="card-header"><h4><i class="bi bi-upload"></i> 导入 URL</h4></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">

                <!-- 文件上传 -->
                <div class="mb-3">
                    <label for="file" class="form-label"><i class="bi bi-file-earmark-arrow-up"></i> 上传文件</label>
                    <input type="file" class="form-control" id="file" name="file" accept=".csv,.txt,.text">
                    <div class="form-text">
                        支持 <code>.csv</code> 和 <code>.txt</code> 文件。CSV 文件取每行第一列为 URL（自动跳过表头）。
                    </div>
                </div>

                <div class="text-center text-muted my-2">
                    <small>— 或者直接粘贴 —</small>
                </div>

                <!-- 文本粘贴 -->
                <div class="mb-3">
                    <label for="urls" class="form-label"><i class="bi bi-clipboard"></i> 粘贴 URL 列表</label>
                    <textarea class="form-control" id="urls" name="urls" rows="10"
                              placeholder="https://example.com
http://another-site.com
https://wp-blog.org"></textarea>
                    <div class="form-text">
                        每行一个 URL，支持 <code>http://</code> 和 <code>https://</code>。
                        未填写协议前缀的将默认使用 <code>https://</code>。
                    </div>
                </div>

                <div class="alert alert-info py-2">
                    <i class="bi bi-info-circle"></i>
                    文件和文本可以同时提交，URL 会自动合并去重。
                    导入后资产为<strong>待扫描</strong>状态，由后台任务自动处理。
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
