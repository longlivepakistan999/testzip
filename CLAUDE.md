# CLAUDE.md - Repository Guide for AI Assistants

## Repository Overview

This repository contains two components:

1. **Emory University Search System** (`search.tgz`) - A legacy PHP/JS institutional search aggregation platform
2. **WP Scanner** (`wp_scanner/`) - A PHP WordPress plugin & theme detection tool with web interface

---

## Project Structure

```
testzip/
├── CLAUDE.md                 # This file
├── search.tgz                # Emory search system archive (PHP/JS)
│
└── wp_scanner/               # WordPress Scanner Application
    ├── index.php             # Dashboard - asset list
    ├── add.php               # Add single asset
    ├── import.php            # Batch import URL list
    ├── detail.php            # Asset detail (plugins/themes/logs)
    ├── scan.php              # Trigger scan action
    ├── delete.php            # Delete asset action
    ├── export.php            # Export JSON/CSV (single or all)
    ├── wp_scanner.db         # SQLite database (auto-created at runtime)
    └── includes/
        ├── config.php        # Database init, CRUD functions, helpers
        ├── scanner.php       # Detection engine (wp-json + HTML parsing)
        ├── header.php        # HTML header + navbar + flash messages
        └── footer.php        # HTML footer + Bootstrap JS
```

---

## WP Scanner - Architecture

### Tech Stack
- **Backend**: PHP 7.4+ (PDO SQLite, curl)
- **Database**: SQLite (file-based, no external DB needed)
- **Frontend**: Bootstrap 5 + Bootstrap Icons (CDN)
- **No framework** — plain PHP with includes

### Detection Methods

The scanner identifies WordPress plugins and themes through:

| Method | Endpoint/Source | Detects |
|--------|----------------|---------|
| wp-json API | `GET /wp-json/` | Plugins (via REST API namespaces) |
| HTML Source | Homepage HTML | Plugins + Themes (via `/wp-content/` paths) |

Note: Version detection for plugins/themes is intentionally not implemented.
Unknown namespaces not in `NAMESPACE_PLUGIN_MAP` are still captured by slug inference from the namespace prefix.

### Key Files

- **`includes/scanner.php`**: Core detection logic
  - `full_scan($asset_id)` - Main entry point, runs all detection methods
  - `scan_wp_json($base_url)` - Fetches `/wp-json/` namespaces
  - `detect_plugins_from_namespaces($ns)` - Maps namespaces to known plugins
  - `detect_plugins_from_html($html)` - Parses HTML for plugin references
  - `detect_themes_from_html($html)` - Parses HTML for theme references
  - `NAMESPACE_PLUGIN_MAP` - Constant array mapping 40+ known namespaces to plugin slugs

- **`includes/config.php`**: Database layer
  - `init_db()` - Creates tables on first run
  - CRUD: `add_asset()`, `get_asset()`, `get_all_assets()`, `delete_asset()`
  - Upsert: `upsert_plugin()`, `upsert_theme()`
  - Helpers: `normalize_url()`, `flash()`, `h()` (XSS-safe output)

- **Page files**: Each PHP file is a self-contained page
  - `index.php` - Dashboard with asset table
  - `add.php` - Single asset form
  - `import.php` - Batch URL import (one per line, http/https)
  - `detail.php` - Asset detail with plugin/theme tables + export
  - `scan.php` / `delete.php` - Action handlers (redirect after)
  - `export.php` - JSON/CSV export (`?id=N&type=json` or `?type=csv`)

### Database Schema

```sql
assets (id, url UNIQUE, name, status, last_scan, created_at)
plugins (id, asset_id FK, slug, name, detected_via, last_seen)
  UNIQUE(asset_id, slug)
themes (id, asset_id FK, slug, name, is_active, detected_via, last_seen)
  UNIQUE(asset_id, slug)
scan_logs (id, asset_id FK, scan_time, status, message)
```

---

## Development Commands

### Setup & Run

```bash
cd wp_scanner
php -S 0.0.0.0:8000
# Open http://127.0.0.1:8000
```

Requirements: PHP 7.4+ with `pdo_sqlite` and `curl` extensions.

### No build/test/lint tooling is configured.

---

## Emory Search System (Legacy)

The `search.tgz` archive contains a PHP-based institutional search gateway:

- **Entry points**: `index.php` (main router), `index-dc.php` (data center variant)
- **Search backends**: Google Custom Search Engine (CSE) + Mindbreeze Enterprise
- **Templates**: `front-ends/standard/` (header, footer, results, no-results)
- **XSLT stylesheets**: `xslt/` directory - 19 stylesheets for different institutional collections
- **Configuration**: `cse.json` (maps URL patterns to Google CSE IDs)
- **Testing**: `collection-testing/` (health checker for search collections)

This is a read-only archive; no active development is expected on it.

---

## Conventions & Guidelines

### Code Style
- **PHP**: Plain PHP, no framework, PSR-adjacent style
- **HTML output**: Use `h()` helper for all dynamic output (XSS prevention)
- **Database**: All SQL in `includes/config.php`, always use PDO prepared statements
- **Page pattern**: Each page includes `config.php` + `scanner.php`, then `header.php` / `footer.php`

### Security Notes
- Scanner uses `CURLOPT_SSL_VERIFYPEER = false` (some WordPress sites have cert issues)
- No authentication on the web interface — add before exposing to network
- Input URLs are normalized via `normalize_url()` — supports both `http://` and `https://`
- All output uses `h()` (htmlspecialchars) to prevent XSS

### Adding New Plugin Detections
Edit `NAMESPACE_PLUGIN_MAP` in `includes/scanner.php`:
```php
const NAMESPACE_PLUGIN_MAP = [
    ...
    'new-plugin/v1' => ['new-plugin-slug', 'New Plugin Display Name'],
];
```

### Adding New Pages
1. Create a new `.php` file in `wp_scanner/`
2. Include `includes/config.php` and `includes/scanner.php`
3. Call `init_db()` at the top
4. Include `includes/header.php` and `includes/footer.php` for layout
5. Add navigation link in `includes/header.php` if needed

### Git Workflow
- Development branch: `claude/claude-md-mkxazjynr7jnvqqj-wZ93x`
- Commit messages should be descriptive
- No CI/CD pipeline is configured

---

## Common Tasks for AI Assistants

1. **Add new detection method**: Modify `includes/scanner.php`, add logic, call from `full_scan()`
2. **Add new export format**: Modify `export.php`, add new `$type` case
3. **Modify UI**: Edit page PHP files, shared layout in `includes/header.php` + `includes/footer.php`
4. **Add database field**: Update schema in `includes/config.php` `init_db()`, update CRUD functions
5. **Extend namespace map**: Add entries to `NAMESPACE_PLUGIN_MAP` in `includes/scanner.php`
6. **Add new page**: Create `.php` file, include config/scanner/header/footer
