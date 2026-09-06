<?php
/**
 * public/ranking.php
 * S.ONE/S.TWO/MASTERS × SHORT/LONG × MEN/WOMEN の9カテゴリを切り替えて表示する。
 * data/ranking.json のみを読み込む。閲覧時にS.LEAGUE公式へは一切アクセスしない。
 *
 * カテゴリ切替はクエリパラメータ(?league=&board=&div=)によるページ遷移で実現しており、
 * JavaScriptは使用していない(0件)。これにより、選択中の1カテゴリ分の行だけが
 * 都度DOMに出力される構造になり、390行を一度に描画することがない。
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/json_store.php';
require_once __DIR__ . '/../inc/view_helpers.php';
require_once __DIR__ . '/../inc/seo_helper.php';
require_once __DIR__ . '/../inc/instagram_helper.php';

apply_robots_header();

// ---- ENABLE_RANKING=false: ページ自体を安全に非表示化する ----
// 404相当のステータスを返しつつ、機能フラグの状態をコードへ埋め込まない形で
// 「今は利用できません」とだけ伝える(存在自体を匂わせすぎない)。
if (!ENABLE_RANKING) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Not Available</title>
<?php if (!allow_indexing()): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif; ?>
<link rel="stylesheet" href="../assets/css/site.css"></head>
<body>
<main class="container"><div class="empty-state">このページは現在利用できません。</div></main>
</body></html>
<?php
    exit;
}

$rankingData = load_ranking_data();
$rankingItems = $rankingData['items'] ?? [];
$availableKeys = array_keys($rankingItems);
$lastUpdated = format_last_updated($rankingData['fetched_at'] ?? null);

$defs = ranking_category_definitions();
$selectedKey = resolve_ranking_category($_GET, $availableKeys);
$scheduleData = load_schedule_data();
$rankingItems = normalize_ranking_round_points_with_schedule($rankingItems, $scheduleData);
$rows = get_ranking_rows($rankingItems, $selectedKey);
$selectedDef = $defs[$selectedKey] ?? $defs[RANKING_DEFAULT_CATEGORY];
$rankingSummary = get_ranking_category_schedule_summary($scheduleData, $selectedKey);

/** カテゴリ切替リンクのURLを組み立てる */
function ranking_tab_url(string $league, ?string $board, ?string $div): string
{
    $params = ['league' => strtolower($league)];
    if ($board) $params['board'] = strtolower($board);
    if ($div) $params['div'] = strtolower($div);
    return 'ranking.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php render_seo_head(build_ranking_title($selectedDef['title'] ?? null), SEO_RANKING_DESCRIPTION, '/ranking.php'); ?>
<link rel="stylesheet" href="../assets/css/site.css">
</head>
<body>

<?php if (IS_DEMO_MODE): ?>
<div class="demo-banner"><?= h(DEMO_BANNER_TEXT) ?></div>
<?php endif; ?>

<header class="site-header">
  <?php render_header_nav('ranking'); ?>
  <div class="site-header__eyebrow">S.LEAGUE 2026-27 SEASON</div>
  <h1 class="site-header__title">RANKING</h1>
</header>

<main class="container">

  <?php if (empty($rankingItems)): ?>
    <div class="empty-state">
      現在ランキング情報を表示できません。<br>
      しばらくしてから再度お試しください。
    </div>
  <?php else: ?>

    <nav class="ranking-tabs" aria-label="リーグ切替">
      <a class="ranking-tabs__link<?= $selectedDef['league'] === 'S1' ? ' is-active' : '' ?>"
         href="<?= h(ranking_tab_url('S1', $selectedDef['board'] ?? 'SHORT', $selectedDef['div'] ?? 'MEN')) ?>">S.ONE</a>
      <a class="ranking-tabs__link<?= $selectedDef['league'] === 'S2' ? ' is-active' : '' ?>"
         href="<?= h(ranking_tab_url('S2', $selectedDef['board'] ?? 'SHORT', $selectedDef['div'] ?? 'MEN')) ?>">S.TWO</a>
      <a class="ranking-tabs__link<?= $selectedDef['league'] === 'MASTERS' ? ' is-active' : '' ?>"
         href="<?= h(ranking_tab_url('MASTERS', null, null)) ?>">MASTERS</a>
    </nav>

    <?php if ($selectedDef['league'] !== 'MASTERS'): ?>
      <nav class="ranking-tabs ranking-tabs--sub" aria-label="種目切替">
        <a class="ranking-tabs__link<?= $selectedDef['board'] === 'SHORT' ? ' is-active' : '' ?>"
           href="<?= h(ranking_tab_url($selectedDef['league'], 'SHORT', $selectedDef['div'])) ?>">SHORT</a>
        <a class="ranking-tabs__link<?= $selectedDef['board'] === 'LONG' ? ' is-active' : '' ?>"
           href="<?= h(ranking_tab_url($selectedDef['league'], 'LONG', $selectedDef['div'])) ?>">LONG</a>
      </nav>
      <nav class="ranking-tabs ranking-tabs--sub" aria-label="性別区分切替">
        <a class="ranking-tabs__link<?= $selectedDef['div'] === 'MEN' ? ' is-active' : '' ?>"
           href="<?= h(ranking_tab_url($selectedDef['league'], $selectedDef['board'], 'MEN')) ?>">MEN</a>
        <a class="ranking-tabs__link<?= $selectedDef['div'] === 'WOMEN' ? ' is-active' : '' ?>"
           href="<?= h(ranking_tab_url($selectedDef['league'], $selectedDef['board'], 'WOMEN')) ?>">WOMEN</a>
      </nav>
    <?php endif; ?>

    <h2 class="ranking-category-title"><?= h($selectedDef['title']) ?></h2>

    <?php if (!empty($rankingSummary)): ?>
      <div class="ranking-summary">
        <div class="ranking-summary__status"><?= h($rankingSummary['current_label'] . ' / ' . $rankingSummary['total_label']) ?></div>
        <div class="season-progress">
          <span class="season-progress__label">SEASON PROGRESS</span>
          <?php foreach ($rankingSummary['rounds'] as $round): ?>
            <span class="season-progress__item season-progress__item--<?= h($round['status']) ?>"><?= h($round['round']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
      <div class="empty-state">このカテゴリのランキングはまだありません。</div>
    <?php else: ?>
      <p class="ranking-scroll-hint">← 横にスライドして各戦ポイントを確認できます →</p>
      <div class="ranking-table__wrapper">
        <table class="ranking-table">
          <thead>
            <tr>
              <th class="ranking-table__th-rank">順位</th>
              <th class="ranking-table__th-name">選手</th>
              <?php
              $columnCount = max(array_merge([0], array_map(fn($row) => count($row['round_points'] ?? []), $rows)));
              for ($col = 1; $col <= $columnCount; $col++):
              ?>
                <th class="ranking-table__th-round">第<?= $col ?>戦</th>
              <?php endfor; ?>
              <th class="ranking-table__th-points">合計</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $row): ?>
              <tr class="<?= $i === 0 || ($rows[$i]['rank'] ?? null) !== ($rows[$i - 1]['rank'] ?? null) ? 'rank-group-start' : '' ?>">
                <td class="ranking-table__rank"><?= h((string) ($row['rank'] ?? '')) ?></td>
                <?php
                  $displayName = ranking_display_name($row, $i);
                  $instagram = sleague_get_verified_instagram($displayName);
                ?>
                <td class="ranking-table__name">
                  <?php if ($instagram): ?>
                    <a href="<?= h($instagram['instagram']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="<?= h($displayName . ' のInstagram') ?>"
                       aria-label="<?= h($displayName . ' のInstagramを開く') ?>"><?= h($displayName) ?> ↗</a>
                  <?php else: ?>
                    <?= h($displayName) ?>
                  <?php endif; ?>
                </td>
                <?php
                $roundPoints = $row['round_points'] ?? [];
                for ($col = 0; $col < $columnCount; $col++):
                    $cell = array_key_exists($col, $roundPoints) ? $roundPoints[$col] : null;
                    $display = $cell === null ? '—' : number_format($cell);
                ?>
                  <td class="ranking-table__points"><?= h($display) ?></td>
                <?php endfor; ?>
                <td class="ranking-table__points"><?= h(number_format($row['points'] ?? 0)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="ranking-table__count"><?= count($rows) ?>名を表示</p>
      <p class="ranking-contact-notice">Instagramリンク切れ・誤リンクの報告 / その他お問い合わせは<a href="contact.php">こちら</a></p>
    <?php endif; ?>

  <?php endif; ?>

</main>

<footer class="site-footer container">
  <div class="site-footer__updated">最終更新　<?= h($lastUpdated) ?></div>
  <div class="site-footer__disclaimer">
    当サイトはS.LEAGUE/JPSAの公式サイトではありません。非公式・非営利のファンサイトです。
    ランキング情報は<?= h($rankingData['source_url'] ?? 'S.LEAGUE公式サイト') ?>を基に自動生成しています。
    最新かつ正確な情報は必ずS.LEAGUE公式サイトをご確認ください。
  </div>
  <a class="site-footer__external-link" href="https://sleague.jp/" target="_blank" rel="noopener">S.LEAGUE公式サイトを見る 〉〉</a>
  <a class="site-footer__contact-link" href="contact.php">お問い合わせ</a>
</footer>

</body>
</html>
