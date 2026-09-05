<?php
/**
 * cron/fetch_latest_video.php
 * S.LEAGUE OFFICIAL YouTube チャンネルの最新動画1本を公式RSSから取得し
 * data/latest_video.json に保存する。YouTube Data APIキー不要。
 *
 * RSS URL: https://www.youtube.com/feeds/videos.xml?channel_id=UCLMnjEjefv0DfKQNuyZyumQ
 * fetch_all.php から呼ばれるほか、単体で直接実行することもできる。
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/http_client.php';
require_once __DIR__ . '/../inc/logger.php';

/**
 * @return array{ok: bool, reason: string}
 */
function run_fetch_latest_video(): array
{
    if (!ENABLE_YOUTUBE) {
        return ['ok' => false, 'reason' => 'ENABLE_YOUTUBE=false のためスキップ'];
    }

    $fetch = fetch_url(YOUTUBE_RSS_URL);

    if (!$fetch['ok']) {
        log_fetch('youtube', false, $fetch['http_code'], 0, $fetch['error'] ?? 'fetch failed');
        return ['ok' => false, 'reason' => $fetch['error'] ?? 'fetch failed'];
    }

    $video = parse_youtube_rss($fetch['body']);

    if ($video === null) {
        log_fetch('youtube', false, $fetch['http_code'], 0, 'RSS parse failed: no valid entry');
        return ['ok' => false, 'reason' => 'RSS parse failed'];
    }

    $result = save_latest_video($video);
    log_fetch('youtube', $result['ok'], $fetch['http_code'], 1, $result['reason']);

    return $result;
}

/**
 * YouTube Atom RSSを解析して最新動画の情報を返す。
 * 失敗した場合はnullを返す（呼び出し元が既存ファイルを維持する）。
 */
function parse_youtube_rss(string $body): ?array
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOERROR);
    libxml_clear_errors();

    if ($xml === false || !isset($xml->entry[0])) {
        return null;
    }

    $entry = $xml->entry[0];
    $ytNs  = $entry->children('http://www.youtube.com/xml/schemas/2015');
    $videoId = (string) $ytNs->videoId;

    // YouTube動画IDは必ず11文字の英数字・ハイフン・アンダースコア
    if (!preg_match('/^[A-Za-z0-9_\-]{11}$/', $videoId)) {
        return null;
    }

    $title = trim((string) $entry->title);
    if ($title === '') {
        $title = 'S.LEAGUE OFFICIAL';
    }

    return [
        'video_id'     => $videoId,
        'title'        => $title,
        'video_url'    => 'https://www.youtube.com/watch?v=' . $videoId,
        'published_at' => (string) $entry->published,
        'channel_name' => 'S.LEAGUE OFFICIAL',
        'channel_url'  => YOUTUBE_CHANNEL_URL,
    ];
}

/**
 * 最新動画データを data/latest_video.json に atomic rename で安全保存。
 */
function save_latest_video(array $video): array
{
    $path = DATA_DIR . '/latest_video.json';

    $payload = [
        'schema_version' => 1,
        'source_url'     => YOUTUBE_RSS_URL,
        'fetched_at'     => date('c'),
        'video_id'       => $video['video_id'],
        'title'          => $video['title'],
        'video_url'      => $video['video_url'],
        'published_at'   => $video['published_at'],
        'channel_name'   => $video['channel_name'],
        'channel_url'    => $video['channel_url'],
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return ['ok' => false, 'reason' => 'json_encode failed: ' . json_last_error_msg()];
    }

    $tmpPath = $path . '.tmp';
    if (file_put_contents($tmpPath, $json, LOCK_EX) === false) {
        return ['ok' => false, 'reason' => "failed writing temp file {$tmpPath}"];
    }

    $reread = json_decode(file_get_contents($tmpPath), true);
    if (!is_array($reread) || empty($reread['video_id'])) {
        @unlink($tmpPath);
        return ['ok' => false, 'reason' => 'temp file re-validation failed'];
    }

    if (!rename($tmpPath, $path)) {
        @unlink($tmpPath);
        return ['ok' => false, 'reason' => "rename failed ({$tmpPath} -> {$path})"];
    }

    return ['ok' => true, 'reason' => "saved video_id={$video['video_id']}"];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $result = run_fetch_latest_video();
    echo $result['ok']
        ? "OK: latest_video.json updated ({$result['reason']})\n"
        : "FAILED: {$result['reason']}\n";
    exit($result['ok'] ? 0 : 1);
}
