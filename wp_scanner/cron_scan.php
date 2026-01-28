#!/usr/bin/env php
<?php
/**
 * Background scan worker — run via cron or CLI.
 *
 * Usage:
 *   php cron_scan.php                          # 串行扫描, 100/批
 *   php cron_scan.php 500                      # 串行扫描, 500/批
 *   php cron_scan.php --parallel 10            # 并行扫描, 10个同时, 100/批
 *   php cron_scan.php --parallel 20 500        # 并行扫描, 20个同时, 500/批
 *   php cron_scan.php --loop                   # 串行, 持续运行
 *   php cron_scan.php --loop --parallel 10     # 并行, 持续运行
 *   php cron_scan.php --loop --parallel 10 200 # 并行, 持续, 200/批
 *
 * Cron example (every minute):
 *   * * * * * php /path/to/wp_scanner/cron_scan.php --parallel 10 200 >> /var/log/wp_scan.log 2>&1
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

// ─── Parse arguments ───

$loop     = in_array('--loop', $argv);
$parallel = 0; // 0 = serial mode
$limit    = 100;

$args = $argv;
array_shift($args); // remove script name
$skip_next = false;

foreach ($args as $i => $arg) {
    if ($skip_next) { $skip_next = false; continue; }
    if ($arg === '--loop') continue;
    if ($arg === '--parallel') {
        // Next arg is concurrency number
        $parallel = (int) ($args[$i + 1] ?? 10);
        $skip_next = true;
        continue;
    }
    if (is_numeric($arg)) {
        $limit = (int) $arg;
    }
}

if ($parallel < 0) $parallel = 0;

function log_msg(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ─── Serial scan (original) ───

function run_batch_serial(int $limit): int
{
    $pending = get_pending_assets($limit);
    $count = count($pending);

    if ($count === 0) {
        log_msg("No pending assets.");
        return 0;
    }

    log_msg("Found $count pending assets, scanning (serial)...");

    $success = 0;
    $errors  = 0;

    foreach ($pending as $asset) {
        $id  = $asset['id'];
        $url = $asset['url'];

        try {
            update_asset_scan($id, 'scanning');
            $result = full_scan($id);

            if (!empty($result['errors'])) {
                log_msg("  [FAIL] #$id $url — " . implode(', ', $result['errors']));
                $errors++;
            } else {
                $pc = count($result['plugins']);
                $tc = count($result['themes']);
                log_msg("  [OK]   #$id $url — $pc plugins, $tc themes");
                $success++;
            }
        } catch (Exception $e) {
            update_asset_scan($id, 'error');
            add_scan_log($id, 'error', $e->getMessage());
            log_msg("  [ERR]  #$id $url — " . $e->getMessage());
            $errors++;
        }
    }

    log_msg("Batch done: $success success, $errors errors.");
    return $count;
}

// ─── Parallel scan ───

function run_batch_parallel(int $limit, int $concurrency): int
{
    // Atomically claim assets to prevent duplicate processing
    $pending = claim_pending_assets($limit);
    $count = count($pending);

    if ($count === 0) {
        log_msg("No pending assets.");
        return 0;
    }

    log_msg("Claimed $count assets, scanning (parallel=$concurrency)...");

    $success = 0;
    $errors  = 0;

    // Process in chunks of $concurrency
    $chunks = array_chunk($pending, $concurrency);

    foreach ($chunks as $ci => $chunk) {
        $chunk_size = count($chunk);
        log_msg("  Chunk " . ($ci + 1) . "/" . count($chunks) . " ($chunk_size assets)...");

        // Build URL list: 2 URLs per asset (homepage + wp-json)
        $urls = [];
        foreach ($chunk as $asset) {
            $base = normalize_url($asset['url']);
            $urls['html_' . $asset['id']]    = $base;
            $urls['wpjson_' . $asset['id']]   = rtrim($base, '/') . '/wp-json/';
        }

        // Concurrent fetch
        $responses = http_multi_get($urls);

        // Process each asset's result
        foreach ($chunk as $asset) {
            $id  = $asset['id'];
            $url = $asset['url'];

            try {
                $html       = $responses['html_' . $id] ?? null;
                $wp_json    = $responses['wpjson_' . $id] ?? null;

                $result = process_asset_scan($id, $html, $wp_json);

                if (!empty($result['errors'])) {
                    log_msg("    [FAIL] #$id $url — " . implode(', ', $result['errors']));
                    $errors++;
                } else {
                    $pc = count($result['plugins']);
                    $tc = count($result['themes']);
                    log_msg("    [OK]   #$id $url — $pc plugins, $tc themes");
                    $success++;
                }
            } catch (Exception $e) {
                update_asset_scan($id, 'error');
                add_scan_log($id, 'error', $e->getMessage());
                log_msg("    [ERR]  #$id $url — " . $e->getMessage());
                $errors++;
            }
        }
    }

    log_msg("Batch done: $success success, $errors errors.");
    return $count;
}

// ─── File Lock (prevent concurrent workers) ───

$lock_file = sys_get_temp_dir() . '/wp_scanner_cron.lock';
$lock_fp = fopen($lock_file, 'w');
if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB)) {
    log_msg("Another worker is already running, exiting.");
    exit(0);
}
fwrite($lock_fp, (string) getmypid());

// Release lock on exit
register_shutdown_function(function () use ($lock_fp, $lock_file) {
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
    @unlink($lock_file);
});

// ─── Main ───

$mode = $parallel > 0 ? "parallel=$parallel" : 'serial';
log_msg("WP Scanner worker started (batch=$limit, $mode, loop=" . ($loop ? 'yes' : 'no') . ")");

if ($loop) {
    while (true) {
        $processed = $parallel > 0
            ? run_batch_parallel($limit, $parallel)
            : run_batch_serial($limit);

        if ($processed === 0) {
            sleep(10);
        } else {
            sleep(2);
        }
    }
} else {
    if ($parallel > 0) {
        run_batch_parallel($limit, $parallel);
    } else {
        run_batch_serial($limit);
    }
}

log_msg("Done.");
