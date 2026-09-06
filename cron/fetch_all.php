<?php
/**
 * cron/fetch_all.php
 * schedule取得とranking取得を1回のcron実行でまとめて行う。
 *
 * 重要: 片方が失敗しても、もう片方の処理・保存には一切影響しない。
 * (それぞれ独立したtry/catchで囲み、safe_save_json()自体も
 *  「検証NGなら何もせず既存ファイルを保持する」設計なので、
 *  一方の異常が他方の正常なJSONを巻き込んで壊すことはない)
 *
 * Lolipopのcron設定でこのファイル1本だけを登録すれば、
 * schedule.json / ranking.json の両方が更新される。
 */

require_once __DIR__ . '/fetch_schedule.php';
require_once __DIR__ . '/fetch_ranking.php';
require_once __DIR__ . '/fetch_latest_video.php';

$results = [];

try {
    $results['schedule'] = run_fetch_schedule();
} catch (Throwable $e) {
    $results['schedule'] = ['ok' => false, 'count' => 0, 'reason' => 'exception: ' . $e->getMessage()];
    log_fetch('schedule', false, 0, 0, 'exception: ' . $e->getMessage());
}

try {
    $results['ranking'] = run_fetch_ranking();
} catch (Throwable $e) {
    $results['ranking'] = ['ok' => false, 'count' => 0, 'reason' => 'exception: ' . $e->getMessage()];
    log_fetch('ranking', false, 0, 0, 'exception: ' . $e->getMessage());
}

try {
    $results['video'] = run_fetch_latest_video();
} catch (Throwable $e) {
    $results['video'] = ['ok' => false, 'reason' => 'exception: ' . $e->getMessage()];
    log_fetch('youtube', false, 0, 0, 'exception: ' . $e->getMessage());
}

echo "schedule: " . ($results['schedule']['ok'] ? "OK ({$results['schedule']['count']} events)" : "FAILED - {$results['schedule']['reason']}") . "\n";
echo "ranking:  " . ($results['ranking']['ok'] ? "OK ({$results['ranking']['count']} rows)" : "FAILED - {$results['ranking']['reason']}") . "\n";
echo "video:    " . ($results['video']['ok'] ? "OK ({$results['video']['reason']})" : "FAILED - {$results['video']['reason']}") . "\n";

// schedule/ranking が両方成功なら0。videoは補助機能なので失敗しても全体終了コードには影響させない。
exit(($results['schedule']['ok'] && $results['ranking']['ok']) ? 0 : 1);
