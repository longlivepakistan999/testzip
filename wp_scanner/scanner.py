"""
WordPress Scanner Module.
Detects plugins and themes via /wp-json/ REST API and HTML source.
"""

import re
import requests
from urllib.parse import urljoin, urlparse
import database as db

# Known wp-json namespace → plugin mapping
NAMESPACE_PLUGIN_MAP = {
    "wp/v2": ("wordpress-core", "WordPress Core"),
    "yoast/v1": ("wordpress-seo", "Yoast SEO"),
    "wc/v1": ("woocommerce", "WooCommerce"),
    "wc/v2": ("woocommerce", "WooCommerce"),
    "wc/v3": ("woocommerce", "WooCommerce"),
    "wc-analytics": ("woocommerce", "WooCommerce"),
    "wc-telemetry": ("woocommerce", "WooCommerce"),
    "jet-cct": ("jetenigne", "JetEngine"),
    "jetpack/v4": ("jetpack", "Jetpack"),
    "wp-site-health/v1": ("wordpress-core", "WordPress Site Health"),
    "wp-block-editor/v1": ("wordpress-core", "WordPress Block Editor"),
    "contact-form-7/v1": ("contact-form-7", "Contact Form 7"),
    "cf7/v1": ("contact-form-7", "Contact Form 7"),
    "acf/v3": ("advanced-custom-fields", "Advanced Custom Fields"),
    "rankmath/v1": ("seo-by-rank-math", "Rank Math SEO"),
    "elementor/v1": ("elementor", "Elementor"),
    "wordfence/v1": ("wordfence", "Wordfence Security"),
    "redirection/v1": ("redirection", "Redirection"),
    "meow-gallery/v1": ("meow-gallery", "Meow Gallery"),
    "ithemes/v1": ("better-wp-security", "iThemes Security"),
    "wpml/v1": ("sitepress-multilingual-cms", "WPML"),
    "regenerate-thumbnails/v1": ("regenerate-thumbnails", "Regenerate Thumbnails"),
    "starter-templates/v1": ("astra-sites", "Starter Templates"),
    "starter-templates/v2": ("astra-sites", "Starter Templates"),
    "akismet/v1": ("akismet", "Akismet"),
    "buddypress/v1": ("buddypress", "BuddyPress"),
    "bbpress/v1": ("bbpress", "bbPress"),
    "tribe/events/v1": ("the-events-calendar", "The Events Calendar"),
    "mec/v1": ("modern-events-calendar-lite", "Modern Events Calendar"),
    "metorik/v1": ("metorik-helper", "Metorik"),
    "monsterinsights/v1": ("google-analytics-for-wordpress", "MonsterInsights"),
    "gravityforms/v2": ("gravityforms", "Gravity Forms"),
    "wpforms/v1": ("wpforms-lite", "WPForms"),
    "updraftplus/v1": ("updraftplus", "UpdraftPlus"),
    "sucuri/v1": ("sucuri-scanner", "Sucuri Security"),
    "wpmailsmtp/v1": ("wp-mail-smtp", "WP Mail SMTP"),
    "fluentform/v1": ("fluentform", "Fluent Forms"),
    "easy-digital-downloads/v1": ("easy-digital-downloads", "Easy Digital Downloads"),
    "lifterlms/v1": ("lifterlms", "LifterLMS"),
    "learndash/v2": ("sfwd-lms", "LearnDash"),
    "amp/v1": ("amp", "AMP"),
    "oembed/1.0": ("wordpress-core", "WordPress oEmbed"),
}

# Request defaults
REQUEST_TIMEOUT = 15
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                  "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
}


def normalize_url(url):
    """Ensure URL has a scheme and no trailing slash."""
    url = url.strip()
    if not url.startswith(("http://", "https://")):
        url = "https://" + url
    return url.rstrip("/")


def scan_wp_json(base_url):
    """
    Scan /wp-json/ endpoint to discover plugins via namespaces.
    Returns: (namespaces_list, raw_json_data) or ([], None) on failure.
    """
    wp_json_url = urljoin(base_url + "/", "wp-json/")
    try:
        resp = requests.get(wp_json_url, headers=HEADERS, timeout=REQUEST_TIMEOUT,
                            allow_redirects=True, verify=False)
        if resp.status_code == 200:
            data = resp.json()
            namespaces = data.get("namespaces", [])
            return namespaces, data
    except (requests.RequestException, ValueError):
        pass
    return [], None


def scan_wp_json_plugins(base_url):
    """
    Try to get plugin list from /wp-json/wp/v2/plugins (usually requires auth).
    Returns list of plugin dicts or empty list.
    """
    url = urljoin(base_url + "/", "wp-json/wp/v2/plugins")
    try:
        resp = requests.get(url, headers=HEADERS, timeout=REQUEST_TIMEOUT,
                            allow_redirects=True, verify=False)
        if resp.status_code == 200:
            return resp.json()
    except (requests.RequestException, ValueError):
        pass
    return []


def detect_plugins_from_namespaces(namespaces):
    """Map wp-json namespaces to known plugins."""
    detected = {}
    for ns in namespaces:
        ns_lower = ns.lower()
        # Direct match
        if ns_lower in NAMESPACE_PLUGIN_MAP:
            slug, name = NAMESPACE_PLUGIN_MAP[ns_lower]
            if slug != "wordpress-core":
                detected[slug] = {"slug": slug, "name": name, "detected_via": f"wp-json namespace: {ns}"}
        else:
            # Try prefix match (e.g., "wc/store/v1" → woocommerce)
            for known_ns, (slug, name) in NAMESPACE_PLUGIN_MAP.items():
                if ns_lower.startswith(known_ns.split("/")[0] + "/"):
                    if slug != "wordpress-core":
                        detected[slug] = {"slug": slug, "name": name, "detected_via": f"wp-json namespace: {ns}"}
                    break
            else:
                # Unknown namespace → likely a plugin
                parts = ns.split("/")
                slug = parts[0].lower()
                if slug not in ("wp", "oembed", "wp-site-health", "wp-block-editor"):
                    detected[slug] = {
                        "slug": slug,
                        "name": slug.replace("-", " ").title(),
                        "detected_via": f"wp-json namespace: {ns}"
                    }
    return detected


def detect_wp_version(html_source, wp_json_data=None):
    """Detect WordPress version from HTML meta tag or wp-json."""
    version = None

    # Try wp-json first
    if wp_json_data:
        # Some installs expose version in the wp-json root
        if "description" in wp_json_data:
            pass  # description doesn't contain version
        # Check generator in head

    # Try HTML meta generator tag
    if html_source:
        match = re.search(
            r'<meta\s+name=["\']generator["\']\s+content=["\']WordPress\s+([\d.]+)["\']',
            html_source, re.IGNORECASE
        )
        if match:
            version = match.group(1)

    return version


def detect_theme_from_html(html_source, base_url):
    """Detect active theme from HTML source by parsing stylesheet links."""
    themes = []
    if not html_source:
        return themes

    # Match /wp-content/themes/{theme-slug}/
    pattern = r'/wp-content/themes/([\w-]+)/'
    matches = set(re.findall(pattern, html_source))

    for slug in matches:
        themes.append({
            "slug": slug,
            "name": slug.replace("-", " ").title(),
            "is_active": 1,
            "detected_via": "HTML source"
        })

    return themes


def detect_plugins_from_html(html_source, base_url):
    """Detect plugins from HTML source by finding /wp-content/plugins/ references."""
    plugins = {}
    if not html_source:
        return plugins

    # Match /wp-content/plugins/{plugin-slug}/
    pattern = r'/wp-content/plugins/([\w-]+)/'
    matches = set(re.findall(pattern, html_source))

    for slug in matches:
        plugins[slug] = {
            "slug": slug,
            "name": slug.replace("-", " ").title(),
            "detected_via": "HTML source (wp-content/plugins/)"
        }

    return plugins


def fetch_homepage(base_url):
    """Fetch the homepage HTML source."""
    try:
        resp = requests.get(base_url, headers=HEADERS, timeout=REQUEST_TIMEOUT,
                            allow_redirects=True, verify=False)
        if resp.status_code == 200:
            return resp.text
    except requests.RequestException:
        pass
    return None


def full_scan(asset_id):
    """
    Perform a full scan on an asset. Detects:
    - Plugins (via wp-json namespaces + HTML source)
    - Themes (via HTML source)
    Stores results in database.
    """
    asset = db.get_asset(asset_id)
    if not asset:
        return {"error": "Asset not found"}

    base_url = normalize_url(asset["url"])
    results = {
        "url": base_url,
        "wp_version": None,
        "plugins": [],
        "themes": [],
        "errors": [],
    }

    # 1. Fetch homepage HTML
    html_source = fetch_homepage(base_url)

    # 2. Scan /wp-json/
    namespaces, wp_json_data = scan_wp_json(base_url)

    if not namespaces and not html_source:
        db.update_asset_scan(asset_id, status="error")
        db.add_scan_log(asset_id, "error", "Could not reach site or no WordPress detected")
        results["errors"].append("Could not reach site or no WordPress detected")
        return results

    # 3. Detect WordPress version
    wp_version = detect_wp_version(html_source, wp_json_data)
    results["wp_version"] = wp_version

    # 4. Detect plugins from wp-json namespaces
    ns_plugins = detect_plugins_from_namespaces(namespaces)

    # 5. Detect plugins from HTML source
    html_plugins = detect_plugins_from_html(html_source, base_url)

    # 6. Merge plugin detections
    all_plugins = {**html_plugins, **ns_plugins}  # namespace data takes priority

    # 7. Detect themes from HTML source
    themes = detect_theme_from_html(html_source, base_url)

    # 8. Store results in database
    for slug, info in all_plugins.items():
        db.upsert_plugin(
            asset_id=asset_id,
            slug=slug,
            name=info.get("name"),
            detected_via=info.get("detected_via"),
        )
        results["plugins"].append(info)

    for theme in themes:
        db.upsert_theme(
            asset_id=asset_id,
            slug=theme["slug"],
            name=theme.get("name"),
            is_active=theme.get("is_active", 0),
            detected_via=theme.get("detected_via"),
        )
        results["themes"].append(theme)

    # 9. Update asset record
    db.update_asset_scan(asset_id, wp_version=wp_version, status="scanned")
    db.add_scan_log(asset_id, "success",
                    f"Found {len(all_plugins)} plugins, {len(themes)} themes")

    return results
