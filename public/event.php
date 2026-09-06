<?php
/**
 * public/event.php
 * 1大会の情報を一画面で確認できる大会ハブ。event.php?id=xxxxx で動的表示する。
 * 大会ごとに個別PHPファイルを作らない(完全自動化を維持する)。
 *
 * データ源はdata/schedule.jsonのみ。閲覧時にS.LEAGUE公式へは一切アクセスしない。
 * 将来 /event/xxxxx/ へのURL Rewriteに変更しやすいよう、
 * ロジック本体はevent_idの受け取り方に依存しない形にしてある(下記$idの取得部分だけ
 * 差し替えれば、クエリ文字列でもパス情報でも対応できる)。
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/json_store.php';
require_once __DIR__ . '/../inc/view_helpers.php';
require_once __DIR__ . '/../inc/status_helper.php';
require_once __DIR__ . '/../inc/seo_helper.php';

apply_robots_header();

// event_id の取得。将来 /event/xxxxx/ にRewriteする場合はここだけ変更すればよい。
$id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $_GET['id']) : '';

$group = null;
$scheduleData = null;

if (ENABLE_SCHEDULE && $id !== '') {
    $scheduleData = load_schedule_data();
    $items = $scheduleData['items'] ?? [];
    $group = find_event_group_by_id($items, $id);
}

// ---- 見つからない場合、機能OFFの場合は安全に404 ----
if (!ENABLE_SCHEDULE || $group === null) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Not Found｜<?= h(SITE_NAME) ?></title>
<?php if (!allow_indexing()): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif; ?>
<link rel="stylesheet" href="../assets/css/site.css"></head>
<body>
<main class="container"><div class="empty-state">
  指定された大会情報が見つかりませんでした。<br>
  <a href="schedule.php" class="home-section__more">スケジュール一覧へ戻る &rsaquo;</a>
</div></main>
</body></html>
<?php
    exit;
}

$statusRaw = $group['status_raw'] ?? null;
$statusClass = status_css_class($statusRaw);
$isLive = $statusClass === 'status-live';
$dayNumber = $isLive ? resolve_day_number($group) : null;
$reserveDay = extract_reserve_day($group['date_text'] ?? null);
$tags = grouped_event_tags($group);

// OFFICIALリンク一覧(メンバーごとに league[+board] をラベル化。代表URLへ勝手に絞らない)
$officialLinks = [];
if (ENABLE_EXTERNAL_LINKS) {
    foreach ($group['members'] as $m) {
        if (empty($m['url'])) {
            continue;
        }
        $label = trim(($m['league'] ?? '') . ' ' . ($m['board'] ?? ''));
        $officialLinks[] = ['label' => $label !== '' ? $label . ' 大会ページ' : '大会ページ', 'url' => $m['url']];
    }
}

// 構造化データ(Event)は、日付が確定しているグループにのみ出す(不明情報を補完しない)
$showStructuredData = !empty($group['date_confirmed']) && !empty($group['start_date']) && !empty($group['name']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
$eventTitle = $group['name'] ?? '大会情報';
render_seo_head(
    build_event_title($eventTitle),
    build_event_description($group),
    '/event.php?id=' . urlencode($id),
    'article'
);
?>
<link rel="stylesheet" href="../assets/css/site.css">
<?php if ($showStructuredData): ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => $group['name'],
    'startDate' => $group['start_date'],
    'endDate' => $group['end_date'],
    'location' => [
        '@type' => 'Place',
        'name' => $group['venue'] ?? '',
    ],
    'eventStatus' => match ($statusRaw) {
        '終了' => 'https://schema.org/EventScheduled',
        '開催中' => 'https://schema.org/EventScheduled',
        '開催予定' => 'https://schema.org/EventScheduled',
        default => 'https://schema.org/EventScheduled',
    },
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php endif; ?>
</head>
<body>

<?php if (IS_DEMO_MODE): ?>
<div class="demo-banner"><?= h(DEMO_BANNER_TEXT) ?></div>
<?php endif; ?>

<header class="site-header">
  <div class="site-header__eyebrow"><a class="home-section__more" href="schedule.php">&lsaquo; SCHEDULEへ戻る</a></div>
  <?php render_header_nav('schedule'); ?>
</header>

<main class="container">

  <div class="home-section">
    <?php if ($isLive): ?>
      <div class="home-hero__flag">EVENT NOW<?php if ($dayNumber): ?> ・ DAY <?= (int) $dayNumber ?><?php endif; ?></div>
    <?php else: ?>
      <span class="status-pill <?= h($statusClass) ?>"><?= h($statusRaw ?? '') ?></span>
    <?php endif; ?>

    <h1 class="event-page__name"><?= h($eventTitle) ?></h1>

    <div class="event-page__date">
      <?= h(format_event_date_range($group)) ?>
      <?php if ($reserveDay): ?><span class="event-page__reserve">予備日 <?= h($reserveDay) ?></span><?php endif; ?>
    </div>

    <p class="event-page__venue"><?= h($group['venue'] ?? '') ?></p>

    <div class="event-card__meta">
      <?php foreach ($tags as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?>
    </div>
  </div>

  <?php if (ENABLE_EXTERNAL_LINKS && !empty($officialLinks)): ?>
    <div class="home-section">
      <h2 class="home-section__title">Official</h2>
      <div class="event-page__official-list">
        <?php foreach ($officialLinks as $link): ?>
          <a class="event-page__official-link" href="<?= h($link['url']) ?>" target="_blank" rel="noopener">
            <?= h($link['label']) ?> &rsaquo;
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="home-section">
    <a class="event-page__official-link event-page__official-link--secondary" href="schedule.php">スケジュール一覧へ戻る &rsaquo;</a>
  </div>

</main>

<footer class="site-footer container">
  <div class="site-footer__updated">最終更新　<?= h(format_last_updated($scheduleData['fetched_at'] ?? null)) ?></div>
  <div class="site-footer__disclaimer">
    当サイトはS.LEAGUE/JPSAの公式サイトではありません。非公式・非営利のファンサイトです。
    大会情報は<?= h($scheduleData['source_url'] ?? 'S.LEAGUE公式サイト') ?>を基に自動生成しています。
    最新かつ正確な情報は必ずS.LEAGUE公式サイトをご確認ください。
  </div>
  <a class="site-footer__contact-link" href="contact.php">お問い合わせ</a>
</footer>

</body>
</html>
