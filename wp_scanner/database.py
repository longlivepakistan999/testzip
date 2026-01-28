"""
Database module for WordPress Scanner.
Handles SQLite operations for assets, plugins, and themes.
"""

import sqlite3
import os
from datetime import datetime

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "wp_scanner.db")


def get_db():
    """Get a database connection with row_factory enabled."""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


def init_db():
    """Initialize the database schema."""
    conn = get_db()
    cursor = conn.cursor()

    cursor.executescript("""
        CREATE TABLE IF NOT EXISTS assets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL UNIQUE,
            name TEXT,
            wp_version TEXT,
            status TEXT DEFAULT 'pending',
            last_scan TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_id INTEGER NOT NULL,
            slug TEXT NOT NULL,
            name TEXT,
            version TEXT,
            description TEXT,
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
            version TEXT,
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
    """)

    conn.commit()
    conn.close()


# ─── Asset Operations ───

def add_asset(url, name=None):
    """Add a new asset (WordPress site) to the database."""
    conn = get_db()
    try:
        conn.execute(
            "INSERT INTO assets (url, name) VALUES (?, ?)",
            (url.rstrip("/"), name or url)
        )
        conn.commit()
        return conn.execute("SELECT last_insert_rowid()").fetchone()[0]
    except sqlite3.IntegrityError:
        return None
    finally:
        conn.close()


def get_all_assets():
    """Get all assets with plugin/theme counts."""
    conn = get_db()
    rows = conn.execute("""
        SELECT a.*,
               (SELECT COUNT(*) FROM plugins WHERE asset_id = a.id) AS plugin_count,
               (SELECT COUNT(*) FROM themes WHERE asset_id = a.id) AS theme_count
        FROM assets a
        ORDER BY a.created_at DESC
    """).fetchall()
    conn.close()
    return rows


def get_asset(asset_id):
    """Get a single asset by ID."""
    conn = get_db()
    row = conn.execute("SELECT * FROM assets WHERE id = ?", (asset_id,)).fetchone()
    conn.close()
    return row


def delete_asset(asset_id):
    """Delete an asset and its related data."""
    conn = get_db()
    conn.execute("DELETE FROM assets WHERE id = ?", (asset_id,))
    conn.commit()
    conn.close()


def update_asset_scan(asset_id, wp_version=None, status="scanned"):
    """Update asset after a scan."""
    conn = get_db()
    conn.execute(
        "UPDATE assets SET wp_version = ?, status = ?, last_scan = ? WHERE id = ?",
        (wp_version, status, datetime.now().isoformat(), asset_id)
    )
    conn.commit()
    conn.close()


# ─── Plugin Operations ───

def upsert_plugin(asset_id, slug, name=None, version=None, description=None, detected_via=None):
    """Insert or update a plugin record."""
    conn = get_db()
    conn.execute("""
        INSERT INTO plugins (asset_id, slug, name, version, description, detected_via, last_seen)
        VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
        ON CONFLICT(asset_id, slug) DO UPDATE SET
            name = COALESCE(excluded.name, name),
            version = COALESCE(excluded.version, version),
            description = COALESCE(excluded.description, description),
            detected_via = COALESCE(excluded.detected_via, detected_via),
            last_seen = datetime('now')
    """, (asset_id, slug, name, version, description, detected_via))
    conn.commit()
    conn.close()


def get_plugins(asset_id):
    """Get all plugins for an asset."""
    conn = get_db()
    rows = conn.execute(
        "SELECT * FROM plugins WHERE asset_id = ? ORDER BY name", (asset_id,)
    ).fetchall()
    conn.close()
    return rows


# ─── Theme Operations ───

def upsert_theme(asset_id, slug, name=None, version=None, is_active=0, detected_via=None):
    """Insert or update a theme record."""
    conn = get_db()
    conn.execute("""
        INSERT INTO themes (asset_id, slug, name, version, is_active, detected_via, last_seen)
        VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
        ON CONFLICT(asset_id, slug) DO UPDATE SET
            name = COALESCE(excluded.name, name),
            version = COALESCE(excluded.version, version),
            is_active = excluded.is_active,
            detected_via = COALESCE(excluded.detected_via, detected_via),
            last_seen = datetime('now')
    """, (asset_id, slug, name, version, is_active, detected_via))
    conn.commit()
    conn.close()


def get_themes(asset_id):
    """Get all themes for an asset."""
    conn = get_db()
    rows = conn.execute(
        "SELECT * FROM themes WHERE asset_id = ? ORDER BY is_active DESC, name", (asset_id,)
    ).fetchall()
    conn.close()
    return rows


# ─── Scan Logs ───

def add_scan_log(asset_id, status, message=""):
    """Add a scan log entry."""
    conn = get_db()
    conn.execute(
        "INSERT INTO scan_logs (asset_id, status, message) VALUES (?, ?, ?)",
        (asset_id, status, message)
    )
    conn.commit()
    conn.close()


def get_scan_logs(asset_id, limit=20):
    """Get recent scan logs for an asset."""
    conn = get_db()
    rows = conn.execute(
        "SELECT * FROM scan_logs WHERE asset_id = ? ORDER BY scan_time DESC LIMIT ?",
        (asset_id, limit)
    ).fetchall()
    conn.close()
    return rows


# ─── Export Helpers ───

def get_asset_full_data(asset_id):
    """Get complete asset data including plugins and themes for export."""
    asset = get_asset(asset_id)
    if not asset:
        return None
    plugins = get_plugins(asset_id)
    themes = get_themes(asset_id)
    return {
        "asset": dict(asset),
        "plugins": [dict(p) for p in plugins],
        "themes": [dict(t) for t in themes],
    }


def get_all_assets_full():
    """Get all assets with full plugin/theme data for bulk export."""
    assets = get_all_assets()
    result = []
    for a in assets:
        data = get_asset_full_data(a["id"])
        if data:
            result.append(data)
    return result
