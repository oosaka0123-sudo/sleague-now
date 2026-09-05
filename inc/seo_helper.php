<?php
/**
 * seo_helper.php
 * title/meta description/canonical/OGPの生成と、検索エンジンへの公開可否判定を担当する。
 *
 * [安全設計] 検索エンジンに実際に公開されるのは、
 *   IS_DEMO_MODE === false かつ ENABLE_PUBLIC_INDEXING === true
 * の両方が揃った時だけ。IS_DEMO_MODEを消し忘れても、ENABLE_PUBLIC_INDEXINGが
 * デフォルトfalseのままなら検索エンジンには公開されない(ONE TOUCHで公開されるのを防ぐ)。
 */

require_once __DIR__ . '/seo_config.php';

/**
 * 検索エンジンへの掲載を許可してよいかどうか。
 */
function allow_indexing(): bool
{
    return IS_DEMO_MODE === false && ENABLE_PUBLIC_INDEXING === true;
}

/**
 * X-Robots-Tagヘッダーを必要な場合のみ送信する。
 * 各ページの先頭(HTML出力より前)で呼び出すこと。
 */
function apply_robots_header(): void
{
    if (!allow_indexing()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
}

function build_home_title(): string
{
    return SITE_NAME . SEO_HOME_TITLE_SUFFIX;
}

/**
 * @param string|null $categoryTitle 選択中カテゴリ名(例: "S.ONE ショートボード メンズ")。nullなら総合title。
 */
function build_ranking_title(?string $categoryTitle = null): string
{
    if ($categoryTitle !== null) {
        return $categoryTitle . ' ' . SEO_RANKING_TITLE_CATEGORY_SUFFIX . '｜' . SITE_NAME;
    }
    return SEO_RANKING_TITLE_BASE . '｜' . SITE_NAME;
}

function build_schedule_title(): string
{
    return SEO_SCHEDULE_TITLE . '｜' . SITE_NAME;
}

/**
 * EVENTページのtitleを大会データから自動生成する(大会ごとの手入力は禁止)。
 */
function build_event_title(string $eventName): string
{
    return $eventName . SEO_EVENT_TITLE_SUFFIX . '｜' . SITE_NAME;
}

/**
 * EVENTページのmeta descriptionを、大会名・開催日・会場・カテゴリから自動生成する。
 * @param array $group find_event_group_by_id()が返すグループ配列
 */
function build_event_description(array $group): string
{
    $parts = [$group['name'] ?? '大会'];
    $parts[] = format_event_date_range($group);
    if (!empty($group['venue'])) {
        $parts[] = $group['venue'];
    }
    $tags = grouped_event_tags($group);
    if (!empty($tags)) {
        $parts[] = implode('/', $tags);
    }
    return implode('｜', $parts) . 'の情報を掲載。' . SITE_NAME . 'で最新状況を確認できます。';
}

/**
 * <head>内に必要なSEO/OGPタグ一式を出力する。
 * canonical/OGP urlはBASE_URLが設定されている場合のみ出力する(デモ中は基本的に出ない)。
 *
 * @param string $title
 * @param string $description
 * @param string $path 例: '/', '/ranking.php', '/schedule.php' (BASE_URLと連結してcanonical/og:urlを作る)
 * @param string $ogType 'website' 等
 */
function render_seo_head(string $title, string $description, string $path, string $ogType = 'website'): void
{
    $canonicalUrl = (BASE_URL !== '') ? rtrim(BASE_URL, '/') . $path : null;

    echo '<title>' . h($title) . "</title>\n";
    echo '<meta name="description" content="' . h($description) . "\">\n";

    if (!allow_indexing()) {
        echo "<meta name=\"robots\" content=\"noindex, nofollow, noarchive\">\n";
    }

    if ($canonicalUrl !== null) {
        echo '<link rel="canonical" href="' . h($canonicalUrl) . "\">\n";
    }

    echo '<meta property="og:locale" content="ja_JP">' . "\n";
    echo '<meta property="og:site_name" content="' . h(SITE_NAME) . "\">\n";
    echo '<meta property="og:title" content="' . h($title) . "\">\n";
    echo '<meta property="og:description" content="' . h($description) . "\">\n";
    echo '<meta property="og:type" content="' . h($ogType) . "\">\n";
    if ($canonicalUrl !== null) {
        echo '<meta property="og:url" content="' . h($canonicalUrl) . "\">\n";
    }
    echo '<meta name="twitter:card" content="summary">' . "\n";
    echo '<meta name="twitter:title" content="' . h($title) . "\">\n";
    echo '<meta name="twitter:description" content="' . h($description) . "\">\n";
    if ($canonicalUrl !== null) {
        echo '<meta name="twitter:url" content="' . h($canonicalUrl) . "\">\n";
    }
}
