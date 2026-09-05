<?php
/**
 * video_helper.php
 * data/latest_video.json から最新YouTube動画情報を読み込む。
 * ページアクセス時に外部通信は一切行わない(cronがキャッシュを更新する)。
 */

/**
 * 最新動画データを返す。ファイルが存在しない・不正な場合はnullを返す。
 * null の場合は呼び出し元でセクション自体を非表示にする。
 */
function load_latest_video(): ?array
{
    $path = DATA_DIR . '/latest_video.json';
    if (!is_file($path)) {
        return null;
    }

    $data = @json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return null;
    }

    // video_id の形式を必ず検証（XSS・不正URL防止）
    $videoId = $data['video_id'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_\-]{11}$/', $videoId)) {
        return null;
    }

    return $data;
}
