<?php
/**
 * public/schedule.php
 * 全大会を開催日順に一本化したSCHEDULE画面。
 * data/schedule.json のみを読み込む。閲覧時にS.LEAGUE公式へは一切アクセスしない。
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/json_store.php';
require_once __DIR__ . '/../inc/view_helpers.php';
require_once __DIR__ . '/../inc/seo_helper.php';

apply_robots_header();

$scheduleData = load_schedule_data();
$items = $scheduleData['items'] ?? [];
$grouped = group_events_for_schedule($items);
$lastUpdated = format_last_updated($scheduleData['fetched_at'] ?? null);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php render_seo_head(build_schedule_title(), SEO_SCHEDULE_DESCRIPTION, '/schedule.php'); ?>
<link rel="stylesheet" href="../assets/css/site.css">
</head>
<body>

<?php if (IS_DEMO_MODE): ?>
<div class="demo-banner"><?= h(DEMO_BANNER_TEXT) ?></div>
<?php endif; ?>

<header class="site-header">
  <?php render_header_nav('schedule'); ?>
  <div class="site-header__eyebrow">S.LEAGUE 2026-27 SEASON</div>
  <h1 class="site-header__title">SCHEDULE</h1>
</header>

<main class="container">

<?php if (!ENABLE_SCHEDULE || empty($items)): ?>
  <div class="empty-state">
    現在スケジュール情報を表示できません。<br>
    しばらくしてから再度お試しください。
  </div>
<?php else: ?>
  <?php foreach ($grouped['by_year'] as $year => $months): ?>
    <div class="year-divider"><?= h($year) ?></div>
    <?php foreach ($months as $monthNum => $events): ?>
      <div class="month-heading"><?= h(MONTH_LABELS_EN[(int) $monthNum]) ?></div>
      <div class="event-list">
        <?php foreach ($events as $event): ?>
          <?php render_event_card($event); ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if (!empty($grouped['tbd'])): ?>
    <div class="tbd-section">
      <div class="tbd-section__heading">日程調整中</div>
      <div class="event-list">
        <?php foreach ($grouped['tbd'] as $event): ?>
          <?php render_event_card($event, true); ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

</main>

<footer class="site-footer container">
  <div class="site-footer__updated">最終更新　<?= h($lastUpdated) ?></div>
  <div class="site-footer__disclaimer">
    当サイトはS.LEAGUE/JPSAの公式サイトではありません。非公式・非営利のファンサイトです。
    大会情報は<?= h($scheduleData['source_url'] ?? 'S.LEAGUE公式サイト') ?>を基に自動生成しています。
    最新かつ正確な情報は必ず<a class="site-footer__link" href="https://sleague.jp/" target="_blank" rel="noopener">S.LEAGUE公式サイト</a>をご確認ください。
  </div>
  <a class="site-footer__contact-link" href="contact.php">お問い合わせ</a>
</footer>

</body>
</html>
<?php
/**
 * 大会カード1件を描画する。
 * $isTbd=true の場合は date_confirmed=false 前提で「日程調整中」表示にする。
 */
function render_event_card(array $event, bool $isTbd = false): void
{
    $tags = event_tags($event);
    $statusClass = status_css_class($event['status_raw'] ?? null);
    $isLive = $statusClass === 'status-live';

    $url = (ENABLE_EXTERNAL_LINKS && !empty($event['url'])) ? $event['url'] : null;
    $tag = $url ? 'a' : 'div';
    $extraAttr = $url ? ' href="' . h($url) . '" target="_blank" rel="noopener"' : '';

    $cardClass = 'event-card' . ($isLive ? ' event-card--live' : '');
    ?>
    <<?= $tag ?> class="<?= h($cardClass) ?>"<?= $extraAttr ?>>
      <div class="date-chip<?= $isTbd ? ' date-chip--tbd' : '' ?>">
        <?php if ($isTbd): ?>
          <span class="date-chip__day">TBD</span>
        <?php else: ?>
          <?php [$m, $d] = explode(' ', format_event_date_range($event), 2); ?>
          <span class="date-chip__month"><?= h($m) ?></span>
          <span class="date-chip__day"><?= h($d) ?></span>
        <?php endif; ?>
      </div>
      <div class="event-card__body">
        <div class="event-card__top">
          <p class="event-card__name">
            <?= h($event['name'] ?? '(大会名不明)') ?>
          </p>
          <span class="status-pill <?= h($statusClass) ?>">
            <?= h($event['status_raw'] ?? '') ?><?php if ($isLive): ?><span class="now-flag">NOW</span><?php endif; ?>
          </span>
        </div>
        <?php if ($isTbd): ?>
          <p class="event-card__tbd-note">
            日程調整中<?php if (!empty($event['venue']) && $event['venue'] !== '調整中'): ?> / <?= h($event['venue']) ?><?php else: ?> / 会場調整中<?php endif; ?>
          </p>
        <?php else: ?>
          <p class="event-card__venue"><?= h($event['venue'] ?? '') ?></p>
        <?php endif; ?>
        <div class="event-card__meta">
          <?php foreach ($tags as $t): ?>
            <span class="tag"><?= h($t) ?></span>
          <?php endforeach; ?>
        </div>
        <?php if ($url): ?>
          <div class="event-card__footer">
            <span class="event-card__detail">詳細を見る 〉〉</span>
          </div>
        <?php endif; ?>
      </div>
    </<?= $tag ?>>
    <?php
}
