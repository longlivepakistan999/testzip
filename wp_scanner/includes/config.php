<?php
/**
 * Database configuration and initialization.
 */

define('DB_PATH', __DIR__ . '/../wp_scanner.db');

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function init_db(): void
{
    $db = get_db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS assets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL UNIQUE,
            name TEXT,
            status TEXT DEFAULT 'pending',
            last_scan TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_id INTEGER NOT NULL,
            slug TEXT NOT NULL,
            name TEXT,
            detected_via TEXT,
            last_seen TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            UNIQUE(asset_id, slug)
        );

        CREATE TABLE IF NOT EXISTS themes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_id INTEGER NOT NULL,
            slug TEXT NOT NULL,
            name TEXT,
            is_active INTEGER DEFAULT 0,
            detected_via TEXT,
            last_seen TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            UNIQUE(asset_id, slug)
        );

        CREATE TABLE IF NOT EXISTS scan_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_id INTEGER NOT NULL,
            scan_time TEXT DEFAULT (datetime('now')),
            status TEXT,
            message TEXT,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        );
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
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            return null;
        }
        throw $e;
    }
}

function get_all_assets(): array
{
    $db = get_db();
    return $db->query("
        SELECT a.*,
               (SELECT COUNT(*) FROM plugins WHERE asset_id = a.id) AS plugin_count,
               (SELECT COUNT(*) FROM themes WHERE asset_id = a.id) AS theme_count
        FROM assets a
        ORDER BY a.created_at DESC
    ")->fetchAll();
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
    $stmt = $db->prepare('UPDATE assets SET status = ?, last_scan = datetime("now") WHERE id = ?');
    $stmt->execute([$status, $id]);
}

// ─── Plugin Operations ───

function upsert_plugin(int $asset_id, string $slug, ?string $name = null, ?string $detected_via = null): void
{
    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO plugins (asset_id, slug, name, detected_via, last_seen)
        VALUES (?, ?, ?, ?, datetime('now'))
        ON CONFLICT(asset_id, slug) DO UPDATE SET
            name = COALESCE(excluded.name, name),
            detected_via = COALESCE(excluded.detected_via, detected_via),
            last_seen = datetime('now')
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

// ─── Theme Operations ───

function upsert_theme(int $asset_id, string $slug, ?string $name = null, int $is_active = 0, ?string $detected_via = null): void
{
    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO themes (asset_id, slug, name, is_active, detected_via, last_seen)
        VALUES (?, ?, ?, ?, ?, datetime('now'))
        ON CONFLICT(asset_id, slug) DO UPDATE SET
            name = COALESCE(excluded.name, name),
            is_active = excluded.is_active,
            detected_via = COALESCE(excluded.detected_via, detected_via),
            last_seen = datetime('now')
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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function get_flashes(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flashes = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $flashes;
}

function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
