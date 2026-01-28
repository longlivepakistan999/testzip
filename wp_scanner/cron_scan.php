#!/usr/bin/env php
<?php
/**
 * Background scan worker — run via cron or CLI.
 *
 * Usage:
 *   php cron_scan.php              # Scan up to 100 pending assets
 *   php cron_scan.php 500          # Scan up to 500 pending assets
 *   php cron_scan.php --loop       # Run continuously (scan 100, sleep 5s, repeat)
 *   php cron_scan.php --loop 200   # Run continuously, 200 per batch
 *
 * Cron example (every minute):
 *   * * * * * php /path/to/wp_scanner/cron_scan.php 200 >> /var/log/wp_scan.log 2>&1
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
init_db();

$loop  = in_array('--loop', $argv);
$limit = 100;

// Parse numeric argument for batch size
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if ($arg === '--loop') continue;
    if (is_numeric($arg)) {
        $limit = (int) $arg;
    }
}

function log_msg(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function run_batch(int $limit): int
{
    $pending = get_pending_assets($limit);
    $count = count($pending);

    if ($count === 0) {
        log_msg("No pending assets.");
        return 0;
    }

    log_msg("Found $count pending assets, scanning...");

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

log_msg("WP Scanner worker started (batch=$limit, loop=" . ($loop ? 'yes' : 'no') . ")");

if ($loop) {
    while (true) {
        $processed = run_batch($limit);
        if ($processed === 0) {
            sleep(10);
        } else {
            sleep(2);
        }
    }
} else {
    run_batch($limit);
}

log_msg("Done.");
