"""
WordPress Scanner - Flask Web Application.
Provides web interface for managing WordPress assets and viewing scan results.
"""

import csv
import io
import json
import threading
from flask import (
    Flask, render_template, request, redirect, url_for,
    flash, jsonify, Response
)

import database as db
import scanner

app = Flask(__name__)
app.secret_key = "wp-scanner-secret-change-in-production"


@app.before_request
def ensure_db():
    """Initialize database on first request."""
    db.init_db()


# ─── Dashboard ───

@app.route("/")
def index():
    """Dashboard - list all assets."""
    assets = db.get_all_assets()
    return render_template("index.html", assets=assets)


# ─── Asset Management ───

@app.route("/add", methods=["GET", "POST"])
def add_asset():
    """Add a new WordPress asset."""
    if request.method == "POST":
        url = request.form.get("url", "").strip()
        name = request.form.get("name", "").strip() or None
        auto_scan = request.form.get("auto_scan") == "on"

        if not url:
            flash("URL is required.", "danger")
            return render_template("add_asset.html")

        url = scanner.normalize_url(url)
        asset_id = db.add_asset(url, name)

        if asset_id is None:
            flash(f"Asset '{url}' already exists.", "warning")
            return redirect(url_for("index"))

        flash(f"Asset '{name or url}' added successfully.", "success")

        if auto_scan:
            # Run scan in background thread
            thread = threading.Thread(target=scanner.full_scan, args=(asset_id,))
            thread.daemon = True
            thread.start()
            flash("Scan started in background.", "info")

        return redirect(url_for("asset_detail", asset_id=asset_id))

    return render_template("add_asset.html")


@app.route("/asset/<int:asset_id>")
def asset_detail(asset_id):
    """View asset details with plugins and themes."""
    asset = db.get_asset(asset_id)
    if not asset:
        flash("Asset not found.", "danger")
        return redirect(url_for("index"))

    plugins = db.get_plugins(asset_id)
    themes = db.get_themes(asset_id)
    logs = db.get_scan_logs(asset_id)

    return render_template("asset_detail.html",
                           asset=asset, plugins=plugins,
                           themes=themes, logs=logs)


@app.route("/asset/<int:asset_id>/delete", methods=["POST"])
def delete_asset(asset_id):
    """Delete an asset."""
    db.delete_asset(asset_id)
    flash("Asset deleted.", "success")
    return redirect(url_for("index"))


# ─── Scanning ───

@app.route("/asset/<int:asset_id>/scan", methods=["POST"])
def scan_asset(asset_id):
    """Trigger a scan for an asset."""
    asset = db.get_asset(asset_id)
    if not asset:
        flash("Asset not found.", "danger")
        return redirect(url_for("index"))

    db.update_asset_scan(asset_id, status="scanning")

    # Run scan in background
    thread = threading.Thread(target=scanner.full_scan, args=(asset_id,))
    thread.daemon = True
    thread.start()

    flash("Scan started. Refresh page in a few seconds to see results.", "info")
    return redirect(url_for("asset_detail", asset_id=asset_id))


@app.route("/api/scan/<int:asset_id>", methods=["POST"])
def api_scan(asset_id):
    """API endpoint: trigger scan and return results synchronously."""
    asset = db.get_asset(asset_id)
    if not asset:
        return jsonify({"error": "Asset not found"}), 404

    results = scanner.full_scan(asset_id)
    return jsonify(results)


# ─── Batch Operations ───

@app.route("/batch-add", methods=["POST"])
def batch_add():
    """Add multiple assets at once (one URL per line)."""
    urls_text = request.form.get("urls", "")
    auto_scan = request.form.get("auto_scan") == "on"
    added = 0
    skipped = 0

    for line in urls_text.strip().splitlines():
        url = line.strip()
        if not url:
            continue
        url = scanner.normalize_url(url)
        asset_id = db.add_asset(url)
        if asset_id:
            added += 1
            if auto_scan:
                thread = threading.Thread(target=scanner.full_scan, args=(asset_id,))
                thread.daemon = True
                thread.start()
        else:
            skipped += 1

    flash(f"Added {added} assets, skipped {skipped} duplicates.", "success")
    return redirect(url_for("index"))


# ─── Export ───

@app.route("/asset/<int:asset_id>/export/json")
def export_json(asset_id):
    """Export single asset data as JSON."""
    data = db.get_asset_full_data(asset_id)
    if not data:
        return jsonify({"error": "Asset not found"}), 404

    return Response(
        json.dumps(data, indent=2, ensure_ascii=False),
        mimetype="application/json",
        headers={"Content-Disposition": f"attachment; filename=asset_{asset_id}.json"}
    )


@app.route("/asset/<int:asset_id>/export/csv")
def export_csv(asset_id):
    """Export single asset plugins/themes as CSV."""
    data = db.get_asset_full_data(asset_id)
    if not data:
        return jsonify({"error": "Asset not found"}), 404

    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(["Type", "Slug", "Name", "Detected Via", "Last Seen"])

    for p in data["plugins"]:
        writer.writerow(["plugin", p["slug"], p["name"],
                         p.get("detected_via", ""), p.get("last_seen", "")])
    for t in data["themes"]:
        writer.writerow(["theme", t["slug"], t["name"],
                         t.get("detected_via", ""), t.get("last_seen", "")])

    csv_content = output.getvalue()
    return Response(
        csv_content,
        mimetype="text/csv",
        headers={"Content-Disposition": f"attachment; filename=asset_{asset_id}.csv"}
    )


@app.route("/export/all/json")
def export_all_json():
    """Export all assets with full data as JSON."""
    data = db.get_all_assets_full()
    return Response(
        json.dumps(data, indent=2, ensure_ascii=False),
        mimetype="application/json",
        headers={"Content-Disposition": "attachment; filename=all_assets.json"}
    )


@app.route("/export/all/csv")
def export_all_csv():
    """Export all assets with plugins/themes as CSV."""
    data = db.get_all_assets_full()

    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(["Site URL", "Site Name", "Type", "Slug",
                     "Name", "Detected Via"])

    for asset_data in data:
        a = asset_data["asset"]
        for p in asset_data["plugins"]:
            writer.writerow([a["url"], a["name"],
                             "plugin", p["slug"], p["name"],
                             p.get("detected_via", "")])
        for t in asset_data["themes"]:
            writer.writerow([a["url"], a["name"],
                             "theme", t["slug"], t["name"],
                             t.get("detected_via", "")])

    return Response(
        output.getvalue(),
        mimetype="text/csv",
        headers={"Content-Disposition": "attachment; filename=all_assets.csv"}
    )


# ─── API Endpoints ───

@app.route("/api/assets")
def api_assets():
    """API: Get all assets."""
    assets = db.get_all_assets()
    return jsonify([dict(a) for a in assets])


@app.route("/api/asset/<int:asset_id>")
def api_asset(asset_id):
    """API: Get single asset with plugins/themes."""
    data = db.get_asset_full_data(asset_id)
    if not data:
        return jsonify({"error": "Asset not found"}), 404
    return jsonify(data)


if __name__ == "__main__":
    db.init_db()
    print("WordPress Scanner starting on http://127.0.0.1:5000")
    app.run(host="0.0.0.0", port=5000, debug=True)
