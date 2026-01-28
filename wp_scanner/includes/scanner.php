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
 * Full scan: detect plugins and themes, store in database.
 */
function full_scan(int $asset_id): array
{
    $asset = get_asset($asset_id);
    if (!$asset) {
        return ['error' => 'Asset not found'];
    }

    $base_url = normalize_url($asset['url']);
    $results = ['url' => $base_url, 'plugins' => [], 'themes' => [], 'errors' => []];

    // 1. Fetch homepage
    $html = http_get($base_url);

    // 2. Scan /wp-json/
    $namespaces = scan_wp_json($base_url);

    if (empty($namespaces) && $html === null) {
        update_asset_scan($asset_id, 'error');
        add_scan_log($asset_id, 'error', 'Could not reach site or no WordPress detected');
        $results['errors'][] = 'Could not reach site or no WordPress detected';
        return $results;
    }

    // 3. Detect plugins from namespaces
    $ns_plugins = detect_plugins_from_namespaces($namespaces);

    // 4. Detect plugins from HTML
    $html_plugins = detect_plugins_from_html($html);

    // 5. Merge (namespace takes priority)
    $all_plugins = array_merge($html_plugins, $ns_plugins);

    // 6. Detect themes from HTML
    $themes = detect_themes_from_html($html);

    // 7. Store in database
    foreach ($all_plugins as $info) {
        upsert_plugin($asset_id, $info['slug'], $info['name'] ?? null, $info['detected_via'] ?? null);
        $results['plugins'][] = $info;
    }

    foreach ($themes as $info) {
        upsert_theme($asset_id, $info['slug'], $info['name'] ?? null, $info['is_active'] ?? 0, $info['detected_via'] ?? null);
        $results['themes'][] = $info;
    }

    // 8. Update asset
    update_asset_scan($asset_id, 'scanned');
    add_scan_log($asset_id, 'success',
        'Found ' . count($all_plugins) . ' plugins, ' . count($themes) . ' themes');

    return $results;
}
