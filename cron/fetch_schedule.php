<?php
/**
 * cron/fetch_schedule.php
 * 本番用: sleague.jp/schedule/ を実際に取得し、data/schedule.jsonを安全更新する。
 *
 * Lolipopのcronから直接 `php /path/to/cron/fetch_schedule.php` として実行される想定。
 * (Webからの直接アクセスはinc/.htaccess等と同様、cron/.htaccessのdeny-allで遮断済み。
 *  cronはHTTP経由ではなくPHP CLIとして直接実行されるため、.htaccessの影響を受けない)
 *
 * 単体実行(このファイルを直接呼んだ場合)でも、fetch_all.phpからinclude_onceされた場合でも
 * 動くように、処理本体を関数化している。
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/http_client.php';
require_once __DIR__ . '/../inc/json_store.php';
require_once __DIR__ . '/../inc/logger.php';
require_once __DIR__ . '/../inc/parser_schedule_sleague.php';

/**
 * @return array{ok: bool, count: int, reason: string}
 */
function run_fetch_schedule(): array
{
    if (!ENABLE_SCHEDULE) {
        log_fetch('schedule', false, 0, 0, 'ENABLE_SCHEDULE=false のためスキップ');
        return ['ok' => false, 'count' => 0, 'reason' => 'ENABLE_SCHEDULE is false'];
    }

    $fetch = fetch_url(URL_SLEAGUE_SCHEDULE);

    if (!$fetch['ok']) {
        log_fetch('schedule', false, $fetch['http_code'], 0, $fetch['error'] ?? 'fetch failed');
        return ['ok' => false, 'count' => 0, 'reason' => $fetch['error'] ?? 'fetch failed'];
    }

    $parsed = parse_schedule_html($fetch['body']);
    $events = $parsed['events'];

    if (!empty($parsed['warnings'])) {
        foreach ($parsed['warnings'] as $w) {
            log_fetch('schedule', true, $fetch['http_code'], count($events), 'warning: ' . $w);
        }
    }

    $save = safe_save_json(DATA_DIR . '/schedule.json', $events, MIN_SCHEDULE_EVENTS, URL_SLEAGUE_SCHEDULE);
    log_fetch('schedule', $save['ok'], $fetch['http_code'], count($events), $save['reason']);

    return ['ok' => $save['ok'], 'count' => count($events), 'reason' => $save['reason']];
}

// fetch_all.php からincludeされた場合は自動実行しない(呼び出し側が明示的にrun_fetch_schedule()を呼ぶ)。
// このファイルを直接実行した場合のみ、その場で走らせる。
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $result = run_fetch_schedule();
    echo $result['ok']
        ? "OK: schedule.json updated ({$result['count']} events)\n"
        : "FAILED: {$result['reason']}\n";
    exit($result['ok'] ? 0 : 1);
}
