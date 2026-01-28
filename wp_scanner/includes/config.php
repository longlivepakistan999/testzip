<?php
/**
 * Database configuration, CRUD operations, pagination helpers.
 * Supports MySQL for large-scale asset management (100k+).
 */

// ─── MySQL Configuration ───
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'wp_scanner');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Pagination
define('PAGE_SIZE', 100);

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function init_db(): void
{
    $db = get_db();

    $db->exec("
        CREATE TABLE IF NOT EXISTS assets (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            url        VARCHAR(2048) NOT NULL,
            name       VARCHAR(512) DEFAULT NULL,
            is_wp      TINYINT      DEFAULT NULL,
            status     VARCHAR(32)  DEFAULT 'pending',
            last_scan  DATETIME     DEFAULT NULL,
            created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_url (url(768)),
            KEY idx_status (status),
            KEY idx_is_wp (is_wp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS plugins (
            id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            asset_id     BIGINT UNSIGNED NOT NULL,
            slug         VARCHAR(256) NOT NULL,
            name         VARCHAR(512) DEFAULT NULL,
            detected_via VARCHAR(512) DEFAULT NULL,
            last_seen    DATETIME     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_asset_slug (asset_id, slug),
            KEY idx_slug (slug),
            CONSTRAINT fk_plugin_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS themes (
            id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            asset_id     BIGINT UNSIGNED NOT NULL,
            slug         VARCHAR(256) NOT NULL,
            name         VARCHAR(512) DEFAULT NULL,
            is_active    TINYINT      DEFAULT 0,
            detected_via VARCHAR(512) DEFAULT NULL,
            last_seen    DATETIME     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_asset_slug (asset_id, slug),
            KEY idx_slug (slug),
            CONSTRAINT fk_theme_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS scan_logs (
            id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            asset_id  BIGINT UNSIGNED NOT NULL,
            scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status    VARCHAR(32),
            message   TEXT,
            KEY idx_asset_time (asset_id, scan_time),
            CONSTRAINT fk_log_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// ─── Asset Operations ───

function add_asset(string $url, ?string $name = null): ?int
{
    $db = get_db();
    $url = rtrim(trim($url), '/');
    $name = $name ?: $url;
    try {
        $stmt = $db->prepare('INSERT INTO assets (url, name) VALUES (?, ?)');
        $stmt->execute([$url, $name]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            return null;
        }
        throw $e;
    }
}

function batch_add_assets(array $urls): array
{
    $db = get_db();
    $stmt = $db->prepare('INSERT IGNORE INTO assets (url, name) VALUES (?, ?)');
    $added = 0;
    $skipped = 0;

    $db->beginTransaction();
    try {
        foreach ($urls as $url) {
            $url = rtrim(trim($url), '/');
            if ($url === '') continue;
            $stmt->execute([$url, $url]);
            if ($stmt->rowCount() > 0) {
                $added++;
            } else {
                $skipped++;
            }
        }
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        throw $e;
    }

    return ['added' => $added, 'skipped' => $skipped];
}

function count_assets(string $search = '', string $status = '', string $plugin = '', string $theme = ''): int
{
    $db = get_db();
    $sql = 'SELECT COUNT(DISTINCT a.id) FROM assets a';
    $joins = '';
    $params = [];

    if ($plugin !== '') {
        $joins .= ' INNER JOIN plugins p ON p.asset_id = a.id AND p.slug LIKE ?';
        $params[] = '%' . $plugin . '%';
    }
    if ($theme !== '') {
        $joins .= ' INNER JOIN themes t ON t.asset_id = a.id AND t.slug LIKE ?';
        $params[] = '%' . $theme . '%';
    }

    $sql .= $joins . ' WHERE 1=1';

    if ($search !== '') {
        $sql .= ' AND (a.url LIKE ? OR a.name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($status !== '') {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function get_assets_page(int $page = 1, string $search = '', string $status = '', string $plugin = '', string $theme = ''): array
{
    $db = get_db();
    $offset = ($page - 1) * PAGE_SIZE;

    $sql = "
        SELECT DISTINCT a.*,
               (SELECT COUNT(*) FROM plugins WHERE asset_id = a.id) AS plugin_count,
               (SELECT COUNT(*) FROM themes WHERE asset_id = a.id) AS theme_count
        FROM assets a
    ";
    $joins = '';
    $params = [];

    if ($plugin !== '') {
        $joins .= ' INNER JOIN plugins p ON p.asset_id = a.id AND p.slug LIKE ?';
        $params[] = '%' . $plugin . '%';
    }
    if ($theme !== '') {
        $joins .= ' INNER JOIN themes t ON t.asset_id = a.id AND t.slug LIKE ?';
        $params[] = '%' . $theme . '%';
    }

    $sql .= $joins . ' WHERE 1=1';

    if ($search !== '') {
        $sql .= ' AND (a.url LIKE ? OR a.name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($status !== '') {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }

    $sql .= ' ORDER BY a.id DESC LIMIT ? OFFSET ?';
    $params[] = PAGE_SIZE;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_asset(int $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM assets WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function delete_asset(int $id): void
{
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM assets WHERE id = ?');
    $stmt->execute([$id]);
}

function update_asset_scan(int $id, string $status = 'scanned'): void
{
    $db = get_db();
    $stmt = $db->prepare('UPDATE assets SET status = ?, last_scan = NOW() WHERE id = ?');
    $stmt->execute([$status, $id]);
}

function mark_asset_wp(int $id, int $is_wp): void
{
    $db = get_db();
    $stmt = $db->prepare('UPDATE assets SET is_wp = ? WHERE id = ?');
    $stmt->execute([$is_wp, $id]);
}

function get_pending_assets(int $limit = 100): array
{
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM assets WHERE status = 'pending' ORDER BY id ASC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function count_assets_by_status(): array
{
    $db = get_db();
    $rows = $db->query("SELECT status, COUNT(*) AS cnt FROM assets GROUP BY status")->fetchAll();
    $result = [];
    foreach ($rows as $r) {
        $result[$r['status']] = (int) $r['cnt'];
    }
    return $result;
}

// ─── Plugin Operations ───

function upsert_plugin(int $asset_id, string $slug, ?string $name = null, ?string $detected_via = null): void
{
    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO plugins (asset_id, slug, name, detected_via, last_seen)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            name = COALESCE(VALUES(name), name),
            detected_via = COALESCE(VALUES(detected_via), detected_via),
            last_seen = NOW()
    ");
    $stmt->execute([$asset_id, $slug, $name, $detected_via]);
}

function get_plugins(int $asset_id): array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM plugins WHERE asset_id = ? ORDER BY name');
    $stmt->execute([$asset_id]);
    return $stmt->fetchAll();
}

function count_plugins(int $asset_id): int
{
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM plugins WHERE asset_id = ?');
    $stmt->execute([$asset_id]);
    return (int) $stmt->fetchColumn();
}

// ─── Theme Operations ───

function upsert_theme(int $asset_id, string $slug, ?string $name = null, int $is_active = 0, ?string $detected_via = null): void
{
    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO themes (asset_id, slug, name, is_active, detected_via, last_seen)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            name = COALESCE(VALUES(name), name),
            is_active = VALUES(is_active),
            detected_via = COALESCE(VALUES(detected_via), detected_via),
            last_seen = NOW()
    ");
    $stmt->execute([$asset_id, $slug, $name, $is_active, $detected_via]);
}

function get_themes(int $asset_id): array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM themes WHERE asset_id = ? ORDER BY is_active DESC, name');
    $stmt->execute([$asset_id]);
    return $stmt->fetchAll();
}

// ─── Scan Logs ───

function add_scan_log(int $asset_id, string $status, string $message = ''): void
{
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO scan_logs (asset_id, status, message) VALUES (?, ?, ?)');
    $stmt->execute([$asset_id, $status, $message]);
}

function get_scan_logs(int $asset_id, int $limit = 20): array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM scan_logs WHERE asset_id = ? ORDER BY scan_time DESC LIMIT ?');
    $stmt->execute([$asset_id, $limit]);
    return $stmt->fetchAll();
}

// ─── Error / Inaccessible Assets ───

function count_error_assets(string $search = ''): int
{
    $db = get_db();
    $sql = "SELECT COUNT(*) FROM assets WHERE status = 'error'";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (url LIKE ? OR name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function get_error_assets_page(int $page = 1, string $search = ''): array
{
    $db = get_db();
    $offset = ($page - 1) * PAGE_SIZE;
    $sql = "SELECT * FROM assets WHERE status = 'error'";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (url LIKE ? OR name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY last_scan DESC, id DESC LIMIT ? OFFSET ?';
    $params[] = PAGE_SIZE;
    $params[] = $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ─── Non-WP Assets ───

function count_notwp_assets(string $search = ''): int
{
    $db = get_db();
    $sql = "SELECT COUNT(*) FROM assets WHERE is_wp = 0";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (url LIKE ? OR name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function get_notwp_assets_page(int $page = 1, string $search = ''): array
{
    $db = get_db();
    $offset = ($page - 1) * PAGE_SIZE;
    $sql = "SELECT * FROM assets WHERE is_wp = 0";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (url LIKE ? OR name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';
    $params[] = PAGE_SIZE;
    $params[] = $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ─── Component (Plugin / Theme) Listings ───

function count_wp_assets(): int
{
    $db = get_db();
    return (int) $db->query("SELECT COUNT(*) FROM assets WHERE is_wp = 1")->fetchColumn();
}

function get_all_plugins_summary(string $search = ''): array
{
    $db = get_db();
    $sql = "SELECT slug, name, COUNT(DISTINCT asset_id) AS asset_count
            FROM plugins";
    $params = [];
    if ($search !== '') {
        $sql .= ' WHERE slug LIKE ? OR name LIKE ?';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " GROUP BY slug, name ORDER BY asset_count DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_all_themes_summary(string $search = ''): array
{
    $db = get_db();
    $sql = "SELECT slug, name, COUNT(DISTINCT asset_id) AS asset_count
            FROM themes";
    $params = [];
    if ($search !== '') {
        $sql .= ' WHERE slug LIKE ? OR name LIKE ?';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " GROUP BY slug, name ORDER BY asset_count DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_assets_by_component(string $type, string $slug): int
{
    $db = get_db();
    $table = $type === 'theme' ? 'themes' : 'plugins';
    $stmt = $db->prepare("SELECT COUNT(DISTINCT asset_id) FROM $table WHERE slug = ?");
    $stmt->execute([$slug]);
    return (int) $stmt->fetchColumn();
}

function get_assets_by_component(string $type, string $slug, int $page = 1): array
{
    $db = get_db();
    $offset = ($page - 1) * PAGE_SIZE;
    $table = $type === 'theme' ? 'themes' : 'plugins';
    $sql = "SELECT a.*,
                   (SELECT COUNT(*) FROM plugins WHERE asset_id = a.id) AS plugin_count,
                   (SELECT COUNT(*) FROM themes WHERE asset_id = a.id) AS theme_count
            FROM assets a
            INNER JOIN $table c ON c.asset_id = a.id AND c.slug = ?
            ORDER BY a.id DESC
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$slug, PAGE_SIZE, $offset]);
    return $stmt->fetchAll();
}

// ─── Helpers ───

function normalize_url(string $url): string
{
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return rtrim($url, '/');
}

function flash(string $message, string $type = 'info'): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function get_flashes(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flashes = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $flashes;
}

function h(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function pagination_info(int $total, int $page): array
{
    $total_pages = max(1, (int) ceil($total / PAGE_SIZE));
    $page = max(1, min($page, $total_pages));
    return [
        'page'        => $page,
        'total'       => $total,
        'total_pages' => $total_pages,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $total_pages,
    ];
}

function render_pagination(array $pager, string $base_query = ''): string
{
    if ($pager['total_pages'] <= 1) return '';

    $sep = $base_query ? '&' : '?';
    $prefix = $base_query ? $base_query . '&' : '?';

    $html = '<nav><ul class="pagination justify-content-center">';

    // Prev
    if ($pager['has_prev']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $prefix . 'page=' . ($pager['page'] - 1) . '">&laquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }

    // Page numbers (show max 7 around current)
    $start = max(1, $pager['page'] - 3);
    $end = min($pager['total_pages'], $pager['page'] + 3);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $prefix . 'page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $pager['page']) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $prefix . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }

    if ($end < $pager['total_pages']) {
        if ($end < $pager['total_pages'] - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $prefix . 'page=' . $pager['total_pages'] . '">' . $pager['total_pages'] . '</a></li>';
    }

    // Next
    if ($pager['has_next']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $prefix . 'page=' . ($pager['page'] + 1) . '">&raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }

    $html .= '</ul></nav>';
    $html .= '<p class="text-center text-muted"><small>共 ' . number_format($pager['total']) . ' 条 / 第 ' . $pager['page'] . ' 页，共 ' . $pager['total_pages'] . ' 页</small></p>';

    return $html;
}
