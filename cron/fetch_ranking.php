<?php
/**
 * cron/fetch_ranking.php
 * 本番用: sleague.jp/ranking/ を実際に取得し、data/ranking.jsonを安全更新する。
 * 全9カテゴリが同一ページ内に存在するため、1回のfetchで全カテゴリ分を取得できる
 * (PHASE 2で確認済み: data-league-panel等のhidden属性で出し分けているだけ)。
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/http_client.php';
require_once __DIR__ . '/../inc/json_store.php';
require_once __DIR__ . '/../inc/logger.php';
require_once __DIR__ . '/../inc/parser_ranking_sleague.php';

/**
 * @return array{ok: bool, count: int, reason: string}
 */
function run_fetch_ranking(): array
{
    if (!ENABLE_RANKING) {
        log_fetch('ranking', false, 0, 0, 'ENABLE_RANKING=false のためスキップ');
        return ['ok' => false, 'count' => 0, 'reason' => 'ENABLE_RANKING is false'];
    }

    $fetch = fetch_url(URL_SLEAGUE_RANKING);

    if (!$fetch['ok']) {
        log_fetch('ranking', false, $fetch['http_code'], 0, $fetch['error'] ?? 'fetch failed');
        return ['ok' => false, 'count' => 0, 'reason' => $fetch['error'] ?? 'fetch failed'];
    }

    $parsed = parse_ranking_html($fetch['body']);
    $ranking = $parsed['ranking'];

    if (!empty($parsed['warnings'])) {
        foreach ($parsed['warnings'] as $w) {
            log_fetch('ranking', true, $fetch['http_code'], count($ranking), 'warning: ' . $w);
        }
    }

    // safe_save_json()は「配列の件数」で検証するため、カテゴリ数(9前後)を渡す。
    // カテゴリ内の行数がゼロのカテゴリはparser側で既に除外済み。
    $save = safe_save_json(DATA_DIR . '/ranking.json', $ranking, MIN_RANKING_CATEGORIES, URL_SLEAGUE_RANKING);
    $totalRows = array_sum(array_map('count', $ranking));
    log_fetch('ranking', $save['ok'], $fetch['http_code'], $totalRows, $save['reason']);

    return ['ok' => $save['ok'], 'count' => $totalRows, 'reason' => $save['reason']];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $result = run_fetch_ranking();
    echo $result['ok']
        ? "OK: ranking.json updated ({$result['count']} rows total)\n"
        : "FAILED: {$result['reason']}\n";
    exit($result['ok'] ? 0 : 1);
}
