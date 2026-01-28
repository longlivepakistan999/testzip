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

    if (!$url) {
        flash('请输入 URL。', 'danger');
    } else {
        $url = normalize_url($url);
        $asset_id = add_asset($url, $name);

        if ($asset_id === null) {
            flash("资产 '$url' 已存在。", 'warning');
            header('Location: index.php');
            exit;
        }

        flash('资产已添加（待扫描）。运行 cron_scan.php 执行扫描。', 'success');
        header("Location: detail.php?id=$asset_id");
        exit;
    }
}

$page_title = '添加资产 - WP Scanner';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-8">
    <div class="card">
        <div class="card-header"><h4><i class="bi bi-plus-circle"></i> 添加资产</h4></div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="url" class="form-label">站点 URL <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="url" name="url" placeholder="https://example.com" required>
                    <div class="form-text">支持 <code>http://</code> 和 <code>https://</code> 格式。</div>
                </div>
                <div class="mb-3">
                    <label for="name" class="form-label">显示名称（可选）</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="我的WordPress站点">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> 添加资产</button>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> 取消</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
