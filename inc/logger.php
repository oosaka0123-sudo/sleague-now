<?php
/**
 * logger.php
 * 取得日時・取得元・成功/失敗・HTTP状態・取得件数・エラー内容を記録する。
 * 無限肥大化しないよう、行数上限を超えたら古い行から切り詰める(簡易ローテーション)。
 */

function log_fetch(string $source, bool $ok, int $httpCode, int $count, string $note = ''): void
{
    $line = sprintf(
        "[%s] source=%s ok=%s http=%d count=%d note=%s\n",
        date('Y-m-d H:i:s'),
        $source,
        $ok ? 'true' : 'false',
        $httpCode,
        $count,
        $note
    );
    append_and_rotate(LOG_DIR . '/fetch.log', $line);

    if (!$ok) {
        append_and_rotate(LOG_DIR . '/error.log', $line);
    }
}

function append_and_rotate(string $file, string $line): void
{
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

    // 行数ローテーション: 上限超えたら末尾LOG_MAX_LINES行だけ残す
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines !== false && count($lines) > LOG_MAX_LINES) {
        $trimmed = array_slice($lines, -1 * LOG_MAX_LINES);
        file_put_contents($file, implode("\n", $trimmed) . "\n", LOCK_EX);
    }
}
