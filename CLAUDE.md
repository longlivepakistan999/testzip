# CLAUDE.md - Repository Guide for AI Assistants

## Repository Overview

This repository contains two components:

1. **Emory University Search System** (`search.tgz`) - A legacy PHP/JS institutional search aggregation platform
2. **WP Scanner** (`wp_scanner/`) - A Python/Flask WordPress plugin & theme detection tool with web interface

---

## Project Structure

```
testzip/
├── CLAUDE.md                 # This file
├── search.tgz                # Emory search system archive (PHP/JS)
│
└── wp_scanner/               # WordPress Scanner Application
    ├── app.py                # Flask web application (routes, views, export)
    ├── scanner.py            # WordPress detection engine (wp-json, HTML parsing)
    ├── database.py           # SQLite database layer (assets, plugins, themes)
    ├── requirements.txt      # Python dependencies (flask, requests)
    ├── wp_scanner.db         # SQLite database (auto-created at runtime)
    ├── static/
    │   └── style.css         # Custom CSS styles
    └── templates/
        ├── base.html         # Base layout (Bootstrap 5, navbar, flash messages)
        ├── index.html        # Dashboard - asset list with counts and actions
        ├── add_asset.html    # Add single asset form + detection info
        └── asset_detail.html # Asset detail with plugins/themes tables + export
```

---

## WP Scanner - Architecture

### Tech Stack
- **Backend**: Python 3 + Flask
- **Database**: SQLite (file-based, no external DB needed)
- **Frontend**: Bootstrap 5 + Bootstrap Icons (CDN)
- **HTTP Client**: `requests` library for WordPress site scanning

### Detection Methods

The scanner identifies WordPress plugins, themes, and versions through:

| Method | Endpoint/Source | Detects |
|--------|----------------|---------|
| wp-json API | `GET /wp-json/` | Plugins (via REST API namespaces) |
| HTML Source | Homepage HTML | Plugins + Themes (via `/wp-content/` paths) |
| style.css | `/wp-content/themes/{slug}/style.css` | Theme name + version |
| readme.txt | `/wp-content/plugins/{slug}/readme.txt` | Plugin version (Stable tag) |
| Meta tag | `<meta name="generator">` | WordPress core version |

### Key Modules

- **`scanner.py`**: Core detection logic
  - `full_scan(asset_id)` - Main entry point, runs all detection methods
  - `scan_wp_json(base_url)` - Fetches `/wp-json/` namespaces
  - `detect_plugins_from_namespaces(ns)` - Maps namespaces to known plugins
  - `detect_plugins_from_html(html, url)` - Parses HTML for plugin references
  - `detect_theme_from_html(html, url)` - Parses HTML for theme references
  - `NAMESPACE_PLUGIN_MAP` - Dict mapping 40+ known namespaces to plugin slugs

- **`database.py`**: SQLite CRUD operations
  - Tables: `assets`, `plugins`, `themes`, `scan_logs`
  - Uses `ON CONFLICT ... DO UPDATE` (upsert) for idempotent scans
  - Foreign keys with `ON DELETE CASCADE`

- **`app.py`**: Flask routes
  - Web UI: `/`, `/add`, `/asset/<id>`, batch add
  - API: `/api/assets`, `/api/asset/<id>`, `/api/scan/<id>`
  - Export: `/asset/<id>/export/json`, `/asset/<id>/export/csv`, `/export/all/json`, `/export/all/csv`

### Database Schema

```sql
assets (id, url UNIQUE, name, wp_version, status, last_scan, created_at)
plugins (id, asset_id FK, slug, name, version, description, detected_via, last_seen)
  UNIQUE(asset_id, slug)
themes (id, asset_id FK, slug, name, version, is_active, detected_via, last_seen)
  UNIQUE(asset_id, slug)
scan_logs (id, asset_id FK, scan_time, status, message)
```

---

## Development Commands

### Setup & Run

```bash
cd wp_scanner
pip install -r requirements.txt
python app.py
# Server starts at http://127.0.0.1:5000
```

### No build/test/lint tooling is configured yet.

To run the scanner standalone:
```bash
cd wp_scanner
python -c "import database as db; db.init_db(); print('DB initialized')"
```

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
- **Python**: Standard Python conventions, no formatter configured
- **Templates**: Jinja2 with Bootstrap 5 classes
- **Database**: All SQL in `database.py`, use parameterized queries (never string interpolation)

### Security Notes
- Scanner uses `verify=False` for HTTPS (some WordPress sites have cert issues)
- `app.secret_key` is hardcoded — change for production deployment
- No authentication on the web interface — add before exposing to network
- Input URLs are normalized but not deeply validated — treat with care

### Adding New Plugin Detections
To add a new wp-json namespace mapping, edit `NAMESPACE_PLUGIN_MAP` in `scanner.py`:
```python
NAMESPACE_PLUGIN_MAP = {
    ...
    "new-plugin/v1": ("new-plugin-slug", "New Plugin Display Name"),
}
```

### Adding New Routes
Follow the existing pattern in `app.py`:
1. Add route function with docstring
2. Use `db.*` functions for database access
3. Use `scanner.*` for scan operations
4. Return `render_template()` for web views, `jsonify()` for API endpoints

### Git Workflow
- Main development branch: `claude/claude-md-mkxazjynr7jnvqqj-wZ93x`
- Commit messages should be descriptive and in English
- No CI/CD pipeline is configured

---

## Common Tasks for AI Assistants

1. **Add new detection method**: Modify `scanner.py`, add detection logic, call from `full_scan()`
2. **Add new export format**: Add route in `app.py`, use `db.get_asset_full_data()` for data
3. **Modify UI**: Edit templates in `wp_scanner/templates/`, base layout in `base.html`
4. **Add database field**: Update schema in `database.py` `init_db()`, update relevant CRUD functions
5. **Extend namespace map**: Add entries to `NAMESPACE_PLUGIN_MAP` in `scanner.py`
