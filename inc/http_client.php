<?php
/**
 * http_client.php
 * 共通HTTP取得ラッパー。全cron取得処理はこれを経由する。
 *
 * 戻り値は常に配列:
 *   [
 *     'ok' => bool,
 *     'http_code' => int,
 *     'body' => string|null,
 *     'error' => string|null,
 *   ]
 */

function fetch_url(string $url, int $timeoutSec = HTTP_TIMEOUT_SEC): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => HTTP_USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: ja,en;q=0.8',
        ],
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $error !== '') {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'body' => null,
            'error' => $error !== '' ? $error : 'unknown curl error',
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'body' => null,
            'error' => "unexpected HTTP status: {$httpCode}",
        ];
    }

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'body' => $body,
        'error' => null,
    ];
}

/**
 * テスト/オフライン用: ローカルHTMLファイルを fetch_url() と同じ形式で返す。
 * サンドボックス環境ではsleague.jp/jpsa.comへ直接アクセスできないため、
 * PHASE 1の検証はこの関数でfixtureを読み込んで行う。
 * 本番(Lolipop)ではfetch_url()をそのまま使う。
 */
function fetch_local_fixture(string $path): array
{
    if (!is_file($path)) {
        return [
            'ok' => false,
            'http_code' => 0,
            'body' => null,
            'error' => "fixture not found: {$path}",
        ];
    }
    $body = file_get_contents($path);
    return [
        'ok' => true,
        'http_code' => 200,
        'body' => $body,
        'error' => null,
    ];
}
