<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'WP Scanner') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
        .card { border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .badge-plugin { background-color: #0d6efd !important; }
        .badge-theme { background-color: #6f42c1 !important; }
        .status-scanned { color: #198754; }
        .status-pending { color: #ffc107; }
        .status-error { color: #dc3545; }
        .status-scanning { color: #0dcaf0; }
        .empty-state { text-align: center; padding: 3rem; color: #6c757d; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; }
        .btn-scan { background: linear-gradient(135deg, #0d6efd, #6610f2); border: none; color: #fff; }
        .btn-scan:hover { background: linear-gradient(135deg, #0b5ed7, #520dc2); color: #fff; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-shield-lock"></i> WP Scanner</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Dashboard</a>
            <a class="nav-link" href="add.php"><i class="bi bi-plus-circle"></i> Add</a>
            <a class="nav-link" href="import.php"><i class="bi bi-upload"></i> Import</a>
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export All
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="export.php?type=json"><i class="bi bi-filetype-json"></i> JSON</a></li>
                    <li><a class="dropdown-item" href="export.php?type=csv"><i class="bi bi-filetype-csv"></i> CSV</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<div class="container">
<?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= h($f['type']) ?> alert-dismissible fade show">
        <?= h($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
