<?php
/**
 * WordPress Scanner - Detection Engine.
 * Detects plugins and themes via /wp-json/ REST API and HTML source.
 */

// Known wp-json namespace → plugin mapping
const NAMESPACE_PLUGIN_MAP = [
    'wp/v2'                      => ['wordpress-core', 'WordPress Core'],
    'yoast/v1'                   => ['wordpress-seo', 'Yoast SEO'],
    'wc/v1'                      => ['woocommerce', 'WooCommerce'],
    'wc/v2'                      => ['woocommerce', 'WooCommerce'],
    'wc/v3'                      => ['woocommerce', 'WooCommerce'],
    'wc-analytics'               => ['woocommerce', 'WooCommerce'],
    'wc-telemetry'               => ['woocommerce', 'WooCommerce'],
    'jet-cct'                    => ['jetengine', 'JetEngine'],
    'jetpack/v4'                 => ['jetpack', 'Jetpack'],
    'wp-site-health/v1'          => ['wordpress-core', 'WordPress Site Health'],
    'wp-block-editor/v1'         => ['wordpress-core', 'WordPress Block Editor'],
    'contact-form-7/v1'          => ['contact-form-7', 'Contact Form 7'],
    'cf7/v1'                     => ['contact-form-7', 'Contact Form 7'],
    'acf/v3'                     => ['advanced-custom-fields', 'Advanced Custom Fields'],
    'rankmath/v1'                => ['seo-by-rank-math', 'Rank Math SEO'],
    'elementor/v1'               => ['elementor', 'Elementor'],
    'wordfence/v1'               => ['wordfence', 'Wordfence Security'],
    'redirection/v1'             => ['redirection', 'Redirection'],
    'meow-gallery/v1'            => ['meow-gallery', 'Meow Gallery'],
    'ithemes/v1'                 => ['better-wp-security', 'iThemes Security'],
    'wpml/v1'                    => ['sitepress-multilingual-cms', 'WPML'],
    'regenerate-thumbnails/v1'   => ['regenerate-thumbnails', 'Regenerate Thumbnails'],
    'starter-templates/v1'       => ['astra-sites', 'Starter Templates'],
    'starter-templates/v2'       => ['astra-sites', 'Starter Templates'],
    'akismet/v1'                 => ['akismet', 'Akismet'],
    'buddypress/v1'              => ['buddypress', 'BuddyPress'],
    'bbpress/v1'                 => ['bbpress', 'bbPress'],
    'tribe/events/v1'            => ['the-events-calendar', 'The Events Calendar'],
    'mec/v1'                     => ['modern-events-calendar-lite', 'Modern Events Calendar'],
    'metorik/v1'                 => ['metorik-helper', 'Metorik'],
    'monsterinsights/v1'         => ['google-analytics-for-wordpress', 'MonsterInsights'],
    'gravityforms/v2'            => ['gravityforms', 'Gravity Forms'],
    'wpforms/v1'                 => ['wpforms-lite', 'WPForms'],
    'updraftplus/v1'             => ['updraftplus', 'UpdraftPlus'],
    'sucuri/v1'                  => ['sucuri-scanner', 'Sucuri Security'],
    'wpmailsmtp/v1'              => ['wp-mail-smtp', 'WP Mail SMTP'],
    'fluentform/v1'              => ['fluentform', 'Fluent Forms'],
    'easy-digital-downloads/v1'  => ['easy-digital-downloads', 'Easy Digital Downloads'],
    'lifterlms/v1'               => ['lifterlms', 'LifterLMS'],
    'learndash/v2'               => ['sfwd-lms', 'LearnDash'],
    'amp/v1'                     => ['amp', 'AMP'],
    'oembed/1.0'                 => ['wordpress-core', 'WordPress oEmbed'],
];

const CORE_NAMESPACES = ['wp', 'oembed', 'wp-site-health', 'wp-block-editor'];

/**
 * HTTP GET request with curl.
 */
function http_get(string $url, int $timeout = 15): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $response !== false) {
        return $response;
    }
    return null;
}

/**
 * Scan /wp-json/ endpoint for namespaces.
 */
function scan_wp_json(string $base_url): array
{
    $json = http_get(rtrim($base_url, '/') . '/wp-json/');
    if ($json === null) {
        return [];
    }
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['namespaces'])) {
        return [];
    }
    return $data['namespaces'];
}

/**
 * Map wp-json namespaces to known plugins.
 */
function detect_plugins_from_namespaces(array $namespaces): array
{
    $detected = [];

    foreach ($namespaces as $ns) {
        $ns_lower = strtolower($ns);

        // Direct match
        if (isset(NAMESPACE_PLUGIN_MAP[$ns_lower])) {
            [$slug, $name] = NAMESPACE_PLUGIN_MAP[$ns_lower];
            if ($slug !== 'wordpress-core') {
                $detected[$slug] = [
                    'slug'         => $slug,
                    'name'         => $name,
                    'detected_via' => "wp-json namespace: $ns",
                ];
            }
            continue;
        }

        // Prefix match (e.g., "wc/store/v1" → woocommerce)
        $matched = false;
        foreach (NAMESPACE_PLUGIN_MAP as $known_ns => [$slug, $name]) {
            $prefix = explode('/', $known_ns)[0] . '/';
            if (strpos($ns_lower, $prefix) === 0) {
                if ($slug !== 'wordpress-core') {
                    $detected[$slug] = [
                        'slug'         => $slug,
                        'name'         => $name,
                        'detected_via' => "wp-json namespace: $ns",
                    ];
                }
                $matched = true;
                break;
            }
        }

        // Unknown namespace → infer as plugin
        if (!$matched) {
            $parts = explode('/', $ns);
            $slug = strtolower($parts[0]);
            if (!in_array($slug, CORE_NAMESPACES, true)) {
                $detected[$slug] = [
                    'slug'         => $slug,
                    'name'         => ucwords(str_replace('-', ' ', $slug)),
                    'detected_via' => "wp-json namespace: $ns",
                ];
            }
        }
    }

    return $detected;
}

/**
 * Detect plugins from HTML source.
 */
function detect_plugins_from_html(?string $html): array
{
    $plugins = [];
    if (!$html) return $plugins;

    if (preg_match_all('#/wp-content/plugins/([\w-]+)/#', $html, $matches)) {
        foreach (array_unique($matches[1]) as $slug) {
            $plugins[$slug] = [
                'slug'         => $slug,
                'name'         => ucwords(str_replace('-', ' ', $slug)),
                'detected_via' => 'HTML source (wp-content/plugins/)',
            ];
        }
    }

    return $plugins;
}

/**
 * Detect themes from HTML source.
 */
function detect_themes_from_html(?string $html): array
{
    $themes = [];
    if (!$html) return $themes;

    if (preg_match_all('#/wp-content/themes/([\w-]+)/#', $html, $matches)) {
        foreach (array_unique($matches[1]) as $slug) {
            $themes[] = [
                'slug'         => $slug,
                'name'         => ucwords(str_replace('-', ' ', $slug)),
                'is_active'    => 1,
                'detected_via' => 'HTML source',
            ];
        }
    }

    return $themes;
}

/**
 * Check if a site is WordPress.
 * Detects via: wp-json response, HTML markers (wp-content, wp-includes, meta generator).
 */
function is_wordpress(?string $html, array $namespaces): bool
{
    // wp-json returned valid namespaces with wp/v2
    if (!empty($namespaces)) {
        foreach ($namespaces as $ns) {
            if (strpos(strtolower($ns), 'wp/') === 0) {
                return true;
            }
        }
    }

    if ($html) {
        // Check for wp-content or wp-includes in HTML
        if (preg_match('#/wp-content/|/wp-includes/#i', $html)) {
            return true;
        }
        // Check meta generator tag
        if (preg_match('#<meta[^>]+content=["\']WordPress#i', $html)) {
            return true;
        }
    }

    return false;
}

/**
 * Concurrent HTTP GET using curl_multi.
 * @param array $urls  Associative array ['key' => 'url', ...]
 * @param int   $timeout  Per-request timeout in seconds
 * @return array ['key' => response_body|null, ...]
 */
function http_multi_get(array $urls, int $timeout = 15): array
{
    if (empty($urls)) return [];

    $mh = curl_multi_init();
    $handles = [];

    foreach ($urls as $key => $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    // Execute all handles concurrently
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh, 1);
        }
    } while ($active && $status === CURLM_OK);

    // Collect results
    $results = [];
    foreach ($handles as $key => $ch) {
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = curl_multi_getcontent($ch);
        $results[$key] = ($code === 200 && $body !== false && $body !== '') ? $body : null;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);
    return $results;
}

/**
 * Process a single asset's scan result given pre-fetched HTML and wp-json response.
 * Used by parallel scanner to avoid redundant HTTP requests.
 */
function process_asset_scan(int $asset_id, ?string $html, ?string $wp_json_body): array
{
    $asset = get_asset($asset_id);
    if (!$asset) {
        return ['error' => 'Asset not found'];
    }

    $results = ['url' => $asset['url'], 'is_wp' => false, 'plugins' => [], 'themes' => [], 'errors' => []];

    // Parse wp-json namespaces
    $namespaces = [];
    if ($wp_json_body !== null) {
        $data = json_decode($wp_json_body, true);
        if (is_array($data) && isset($data['namespaces'])) {
            $namespaces = $data['namespaces'];
        }
    }

    if ($html === null && empty($namespaces)) {
        update_asset_scan($asset_id, 'error');
        mark_asset_wp($asset_id, 0);
        add_scan_log($asset_id, 'error', 'Could not reach site');
        $results['errors'][] = 'Could not reach site';
        return $results;
    }

    // Check if WordPress
    $is_wp = is_wordpress($html, $namespaces);
    $results['is_wp'] = $is_wp;
    mark_asset_wp($asset_id, $is_wp ? 1 : 0);

    if (!$is_wp) {
        update_asset_scan($asset_id, 'not_wp');
        add_scan_log($asset_id, 'info', 'Not a WordPress site, skipping plugin/theme detection');
        return $results;
    }

    // Detect plugins from namespaces + HTML
    $ns_plugins   = detect_plugins_from_namespaces($namespaces);
    $html_plugins = detect_plugins_from_html($html);
    $all_plugins  = array_merge($html_plugins, $ns_plugins);

    // Detect themes from HTML
    $themes = detect_themes_from_html($html);

    // Store in database
    foreach ($all_plugins as $info) {
        upsert_plugin($asset_id, $info['slug'], $info['name'] ?? null, $info['detected_via'] ?? null);
        $results['plugins'][] = $info;
    }
    foreach ($themes as $info) {
        upsert_theme($asset_id, $info['slug'], $info['name'] ?? null, $info['is_active'] ?? 0, $info['detected_via'] ?? null);
        $results['themes'][] = $info;
    }

    update_asset_scan($asset_id, 'scanned');
    add_scan_log($asset_id, 'success',
        'Found ' . count($all_plugins) . ' plugins, ' . count($themes) . ' themes');

    return $results;
}

/**
 * Full scan: detect if WordPress, then detect plugins and themes, store in database.
 */
function full_scan(int $asset_id): array
{
    $asset = get_asset($asset_id);
    if (!$asset) {
        return ['error' => 'Asset not found'];
    }

    $base_url = normalize_url($asset['url']);
    $results = ['url' => $base_url, 'is_wp' => false, 'plugins' => [], 'themes' => [], 'errors' => []];

    // 1. Fetch homepage
    $html = http_get($base_url);

    // 2. Scan /wp-json/
    $namespaces = scan_wp_json($base_url);

    if ($html === null && empty($namespaces)) {
        update_asset_scan($asset_id, 'error');
        mark_asset_wp($asset_id, 0);
        add_scan_log($asset_id, 'error', 'Could not reach site');
        $results['errors'][] = 'Could not reach site';
        return $results;
    }

    // 3. Check if WordPress
    $is_wp = is_wordpress($html, $namespaces);
    $results['is_wp'] = $is_wp;
    mark_asset_wp($asset_id, $is_wp ? 1 : 0);

    if (!$is_wp) {
        update_asset_scan($asset_id, 'not_wp');
        add_scan_log($asset_id, 'info', 'Not a WordPress site, skipping plugin/theme detection');
        return $results;
    }

    // 4. Detect plugins from namespaces
    $ns_plugins = detect_plugins_from_namespaces($namespaces);

    // 5. Detect plugins from HTML
    $html_plugins = detect_plugins_from_html($html);

    // 6. Merge (namespace takes priority)
    $all_plugins = array_merge($html_plugins, $ns_plugins);

    // 7. Detect themes from HTML
    $themes = detect_themes_from_html($html);

    // 8. Store in database
    foreach ($all_plugins as $info) {
        upsert_plugin($asset_id, $info['slug'], $info['name'] ?? null, $info['detected_via'] ?? null);
        $results['plugins'][] = $info;
    }

    foreach ($themes as $info) {
        upsert_theme($asset_id, $info['slug'], $info['name'] ?? null, $info['is_active'] ?? 0, $info['detected_via'] ?? null);
        $results['themes'][] = $info;
    }

    // 9. Update asset
    update_asset_scan($asset_id, 'scanned');
    add_scan_log($asset_id, 'success',
        'Found ' . count($all_plugins) . ' plugins, ' . count($themes) . ' themes');

    return $results;
}
