<?php
/**
 * public/index.php
 * 「S.LEAGUEの今が3秒で分かる競技ダッシュボード」。
 * data/schedule.json と data/ranking.json のみを使用し、閲覧時に外部アクセスしない。
 * CURRENT/NEXTの判定はinc/status_helper.phpの共通ロジックをSCHEDULE画面と共用する
 * (HOME独自の判定ロジックは作らない)。
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/json_store.php';
require_once __DIR__ . '/../inc/view_helpers.php';
require_once __DIR__ . '/../inc/status_helper.php';
require_once __DIR__ . '/../inc/seo_helper.php';
require_once __DIR__ . '/../inc/video_helper.php';

apply_robots_header();

// ---- スケジュール関連(ENABLE_SCHEDULEに従う) ----
$currentEvent = null;
$nextEvent = null;
$upcomingEvents = [];
$scheduleUpdated = null;

if (ENABLE_SCHEDULE) {
    $scheduleData = load_schedule_data();
    $scheduleItems = $scheduleData['items'] ?? [];
    $scheduleUpdated = format_last_updated($scheduleData['fetched_at'] ?? null);

    // HOME表示専用: 大会名/start_date/end_date/venueが完全一致するイベントのみ
    // 表示上でグループ化する(schedule.json自体は書き換えない)。
    // 日付未確定(調整中)のイベントは元々CURRENT/NEXT/UPCOMINGの対象外なので、
    // 確定日イベントだけを対象にグルーピングする。
    $confirmedItems = array_values(array_filter($scheduleItems, fn($e) => $e['date_confirmed'] ?? false));
    $groupedConfirmed = group_events_for_home($confirmedItems);

    $currentEvent = find_current_event($groupedConfirmed);
    $nextEvent = find_next_event($groupedConfirmed);
    $upcomingEvents = find_upcoming_events($groupedConfirmed, 5);
}

// ---- YouTube最新動画(ENABLE_YOUTUBEに従う) ----
$latestVideo = null;
if (ENABLE_YOUTUBE) {
    $latestVideo = load_latest_video();
}

// ---- ランキング関連(ENABLE_RANKINGに従う) ----
$top5 = [];
$rankingUpdated = null;

if (ENABLE_RANKING) {
    $rankingData = load_ranking_data();
    $rankingItems = $rankingData['items'] ?? [];
    $rankingUpdated = format_last_updated($rankingData['fetched_at'] ?? null);
    $top5 = get_ranking_top($rankingItems, RANKING_DEFAULT_CATEGORY, 5);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php render_seo_head(build_home_title(), SEO_HOME_DESCRIPTION, '/'); ?>
<link rel="stylesheet" href="../assets/css/site.css">
</head>
<body>

<?php if (IS_DEMO_MODE): ?>
<div class="demo-banner"><?= h(DEMO_BANNER_TEXT) ?></div>
<?php endif; ?>

<header class="site-header">
  <?php render_header_nav('home'); ?>
  <div class="site-header__eyebrow"><?= h(SITE_TAGLINE) ?></div>
  <h1 class="site-header__title"><?= h(SITE_NAME) ?></h1>
</header>

<main class="container">

<?php if (ENABLE_SCHEDULE && $currentEvent): ?>
  <div class="home-section">
    <div class="home-section__heading">
      <h2 class="home-section__title">Event Now</h2>
    </div>
    <?php
      // 内部EVENTページへは常にリンクする(当サイト自身のページなのでENABLE_EXTERNAL_LINKSの対象外)。
      $heroId = event_id_for($currentEvent['members'][0] ?? $currentEvent);
      $heroUrl = 'event.php?id=' . urlencode($heroId);
    ?>
    <a class="home-hero" href="<?= h($heroUrl) ?>">
      <div class="home-hero__flag">EVENT NOW<?php if (!empty($currentEvent['day_number'])): ?> ・ DAY <?= (int) $currentEvent['day_number'] ?><?php endif; ?></div>
      <h3 class="home-hero__name"><?= h($currentEvent['name'] ?? '(大会名不明)') ?></h3>
      <div class="home-hero__meta">
        <span><?= h(format_event_date_range($currentEvent)) ?></span>
        <span><?= h($currentEvent['venue'] ?? '') ?></span>
        <?php foreach (grouped_event_tags($currentEvent) as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?>
      </div>
    </a>
  </div>
<?php endif; ?>

<?php if (ENABLE_YOUTUBE && !empty($latestVideo)): ?>
  <div class="home-section">
    <div class="home-section__heading">
      <h2 class="home-section__title">Latest Video</h2>
    </div>
    <div class="home-video">
      <iframe
        src="https://www.youtube.com/embed/<?= h($latestVideo['video_id']) ?>?rel=0"
        title="<?= h($latestVideo['title'] ?? 'S.LEAGUE OFFICIAL') ?>"
        allowfullscreen
        loading="lazy"
      ></iframe>
    </div>
    <div class="home-video__footer">
      <a class="home-video__link"
         href="<?= h(YOUTUBE_CHANNEL_URL) ?>"
         target="_blank"
         rel="noopener noreferrer">S.LEAGUE OFFICIALで見る &rsaquo;</a>
    </div>
  </div>
<?php endif; ?>

<?php if (ENABLE_SCHEDULE && $nextEvent): ?>
  <div class="home-section">
    <div class="home-section__heading">
      <h2 class="home-section__title">Next Event</h2>
    </div>
    <?php
      $nextId = event_id_for($nextEvent['members'][0] ?? $nextEvent);
      $nextUrl = 'event.php?id=' . urlencode($nextId);
    ?>
    <a class="home-next" href="<?= h($nextUrl) ?>">
      <span class="home-next__countdown">あと<?= (int) $nextEvent['days_until'] ?>日</span>
      <h3 class="home-next__name"><?= h($nextEvent['name'] ?? '(大会名不明)') ?></h3>
      <div class="home-next__meta">
        <span><?= h(format_event_date_range($nextEvent)) ?></span>
        <span><?= h($nextEvent['venue'] ?? '') ?></span>
        <?php foreach (grouped_event_tags($nextEvent) as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?>
      </div>
    </a>
  </div>
<?php endif; ?>

<?php if (ENABLE_RANKING && !empty($top5)): ?>
  <div class="home-section">
    <div class="home-section__heading">
      <h2 class="home-section__title">Ranking Top5</h2>
      <a class="home-section__more" href="ranking.php">全ランキングを見る &rsaquo;</a>
    </div>
    <table class="ranking-table">
      <thead>
        <tr>
          <th class="ranking-table__th-rank">順位</th>
          <th class="ranking-table__th-name">選手</th>
          <?php if (ENABLE_POINTS): ?><th class="ranking-table__th-points">ポイント</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($top5 as $i => $row): ?>
          <tr class="<?= $i === 0 || ($top5[$i]['rank'] ?? null) !== ($top5[$i - 1]['rank'] ?? null) ? 'rank-group-start' : '' ?>">
            <td class="ranking-table__rank"><?= h((string) ($row['rank'] ?? '')) ?></td>
            <td class="ranking-table__name"><?= h(ranking_display_name($row, $i)) ?></td>
            <?php if (ENABLE_POINTS): ?>
              <td class="ranking-table__points"><?= h(number_format($row['points'] ?? 0)) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if (ENABLE_SCHEDULE && !empty($upcomingEvents)): ?>
  <div class="home-section">
    <div class="home-section__heading">
      <h2 class="home-section__title">Upcoming Schedule</h2>
      <a class="home-section__more" href="schedule.php">全スケジュールを見る &rsaquo;</a>
    </div>
    <div class="home-upcoming-list">
      <?php foreach ($upcomingEvents as $e): ?>
        <?php $itemId = event_id_for($e['members'][0] ?? $e); ?>
        <a class="home-upcoming-item" href="event.php?id=<?= h(urlencode($itemId)) ?>">
          <span class="home-upcoming-item__date"><?= h(format_event_date_range($e)) ?></span>
          <span class="home-upcoming-item__name"><?= h($e['name'] ?? '(大会名不明)') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!ENABLE_SCHEDULE && !ENABLE_RANKING): ?>
  <div class="empty-state">現在表示できる情報がありません。</div>
<?php endif; ?>

</main>

<footer class="site-footer container">
  <div class="home-footer-updated">
    <?php if (ENABLE_SCHEDULE): ?><span>Schedule updated: <?= h($scheduleUpdated ?? '-') ?></span><?php endif; ?>
    <?php if (ENABLE_RANKING): ?><span>Ranking updated: <?= h($rankingUpdated ?? '-') ?></span><?php endif; ?>
  </div>
  <div class="site-footer__disclaimer">
    当サイトはS.LEAGUE/JPSAの公式サイトではありません。非公式・非営利のファンサイトです。
    最新かつ正確な情報は必ずS.LEAGUE公式サイトをご確認ください。
  </div>
  <a class="site-footer__external-link" href="https://sleague.jp/" target="_blank" rel="noopener">S.LEAGUE公式サイトを見る 〉〉</a>
  <a class="site-footer__contact-link" href="contact.php">お問い合わせ</a>
</footer>

</body>
</html>
