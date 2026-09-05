<?php
/**
 * json_store.php
 * 「取得失敗→空JSON保存→データ全消失」を絶対に起こさないための安全保存レイヤー。
 *
 * 流れ:
 *   新データ検証(件数・必須フィールド) → OKなら一時ファイルに書く
 *   → JSONとして正しくデコードできるか再確認 → 既存ファイルとatomic rename置換
 *   → メタデータ(fetched_at等)を添えて保存
 *
 * 検証NGの場合は何もせず既存ファイルを維持し、false を返す。
 */

/**
 * @param string $path 保存先 (例: DATA_DIR.'/schedule.json')
 * @param array $records 保存したいレコード配列 (中身は呼び出し側で件数チェック済み想定だが、ここでも二重チェックする)
 * @param int $minCount これ未満なら異常とみなし保存しない
 * @param string $sourceUrl メタデータ用
 * @return array ['ok'=>bool, 'reason'=>string]
 */
function safe_save_json(string $path, array $records, int $minCount, string $sourceUrl): array
{
    $count = count($records);

    if ($count < $minCount) {
        return ['ok' => false, 'reason' => "record count too low ({$count} < {$minCount}); keeping previous data"];
    }

    $payload = [
        'schema_version' => 1,
        'source_url' => $sourceUrl,
        'fetched_at' => date('c'),
        'data_count' => $count,
        'items' => $records,
    ];

    // 既存ファイルがあれば last_success を引き継ぐ
    if (is_file($path)) {
        $prev = json_decode(file_get_contents($path), true);
        if (is_array($prev) && isset($prev['fetched_at'])) {
            $payload['last_success'] = $prev['fetched_at'];
        }
    }
    if (!isset($payload['last_success'])) {
        $payload['last_success'] = $payload['fetched_at'];
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return ['ok' => false, 'reason' => 'json_encode failed: ' . json_last_error_msg()];
    }

    $tmpPath = $path . '.tmp';
    $bytesWritten = file_put_contents($tmpPath, $json, LOCK_EX);
    if ($bytesWritten === false) {
        return ['ok' => false, 'reason' => "failed writing temp file {$tmpPath}"];
    }

    // 書いたtmpファイルが正しいJSONとして読めるか再検証してから置換
    $reread = json_decode(file_get_contents($tmpPath), true);
    if (!is_array($reread) || !isset($reread['items'])) {
        @unlink($tmpPath);
        return ['ok' => false, 'reason' => 'temp file failed re-validation; aborted before replacing existing data'];
    }

    if (!rename($tmpPath, $path)) {
        @unlink($tmpPath);
        return ['ok' => false, 'reason' => "rename failed ({$tmpPath} -> {$path})"];
    }

    return ['ok' => true, 'reason' => 'saved'];
}

function load_json(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}
