# CLAUDE.md - Repository Guide for AI Assistants

## Repository Overview

This repository contains two components:

1. **Emory University Search System** (`search.tgz`) - Legacy PHP/JS institutional search aggregation platform
2. **WP Scanner** (`wp_scanner/`) - PHP WordPress plugin & theme detection tool, designed for large-scale (100k+) asset management

---

## Project Structure

```
testzip/
├── CLAUDE.md                 # This file
├── search.tgz                # Emory search system archive (read-only)
│
└── wp_scanner/               # WordPress Scanner Application
    ├── index.php             # Dashboard - paginated list + search/filter
    ├── add.php               # Add single asset
    ├── import.php            # Batch import URL list
    ├── detail.php            # Asset detail (plugins/themes/logs)
    ├── scan.php              # Trigger single asset scan
    ├── delete.php            # Delete asset
    ├── export.php            # Export JSON/CSV (single or all)
    ├── cron_scan.php         # CLI background scan worker
    └── includes/
        ├── config.php        # MySQL DB, CRUD, pagination, batch ops
        ├── scanner.php       # Detection engine (WP check + wp-json + HTML)
        ├── header.php        # HTML header + navbar
        └── footer.php        # HTML footer
```

---

## WP Scanner - Architecture

### Tech Stack
- **Backend**: PHP 7.4+ (PDO MySQL, curl)
- **Database**: MySQL / MariaDB (InnoDB, utf8mb4)
- **Frontend**: Bootstrap 5 + Bootstrap Icons (CDN)

### Scale Design
- MySQL with indexes on url, status, is_wp, slug fields
- Paginated queries (100 per page)
- Async scanning via CLI worker (`cron_scan.php`)
- Batch insert with `INSERT IGNORE` + transactions
- Search/filter by URL, plugin slug, theme slug, status

### Detection Flow

```
1. Fetch homepage HTML + /wp-json/
2. is_wordpress() check:
   - wp-json has wp/v2 namespace?
   - HTML contains /wp-content/ or /wp-includes/?
   - <meta generator="WordPress">?
3. If NOT WordPress → mark as not_wp, skip
4. If WordPress → detect plugins + themes
```

### Detection Methods

| Method | Endpoint/Source | Detects |
|--------|----------------|---------|
| WP check | HTML + wp-json | Whether site is WordPress |
| wp-json API | `GET /wp-json/` | Plugins (via REST API namespaces) |
| HTML Source | Homepage HTML | Plugins + Themes (via `/wp-content/` paths) |

### Key Files

- **`includes/scanner.php`**: Detection engine
  - `is_wordpress($html, $namespaces)` - WordPress detection
  - `full_scan($asset_id)` - Main entry (WP check → plugin/theme detect)
  - `NAMESPACE_PLUGIN_MAP` - 40+ namespace-to-plugin mappings

- **`includes/config.php`**: Database + business logic
  - MySQL via env vars (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`)
  - `batch_add_assets($urls)` - Transactional bulk insert
  - `get_assets_page($page, $search, $status, $plugin, $theme)` - Filtered pagination
  - `mark_asset_wp($id, $is_wp)` - Set WordPress detection result
  - `render_pagination()` - HTML pagination component

- **`cron_scan.php`**: Background worker
  - `php cron_scan.php` — 100 pending
  - `php cron_scan.php 500` — 500 pending
  - `php cron_scan.php --loop` — continuous
  - `php cron_scan.php --loop 200` — continuous, 200/batch

### Database Schema (MySQL)

```sql
assets (id BIGINT PK, url UNIQUE, name, is_wp TINYINT, status, last_scan, created_at)
  INDEX: idx_status, idx_is_wp

plugins (id BIGINT PK, asset_id FK, slug, name, detected_via, last_seen)
  UNIQUE: (asset_id, slug), INDEX: idx_slug

themes (id BIGINT PK, asset_id FK, slug, name, is_active, detected_via, last_seen)
  UNIQUE: (asset_id, slug), INDEX: idx_slug

scan_logs (id BIGINT PK, asset_id FK, scan_time, status, message)
  INDEX: (asset_id, scan_time)
```

---

## Setup

```bash
# 1. Create MySQL database
mysql -u root -e "CREATE DATABASE wp_scanner CHARACTER SET utf8mb4;"

# 2. Configure
export DB_HOST=127.0.0.1 DB_USER=root DB_PASS=yourpassword DB_NAME=wp_scanner

# 3. Start web
cd wp_scanner && php -S 0.0.0.0:8000

# 4. Background scan
php cron_scan.php --loop
```

Cron: `* * * * * php /path/to/cron_scan.php 200 >> /var/log/wp_scan.log 2>&1`

---

## Conventions

- Use `h()` for all HTML output
- All SQL in `includes/config.php`, PDO prepared statements only
- Page pattern: require config + scanner → init_db() → header/footer
- Namespace mappings in `NAMESPACE_PLUGIN_MAP` constant

---

## Common Tasks

1. **Add namespace mapping**: Edit `NAMESPACE_PLUGIN_MAP` in `includes/scanner.php`
2. **Add export format**: Add `$type` case in `export.php`
3. **Add search filter**: Update `count_assets()` + `get_assets_page()`, add form field
4. **Add database field**: Update `init_db()` schema + CRUD functions
5. **Change page size**: Edit `PAGE_SIZE` in `includes/config.php`
