<?php
/**
 * parser_schedule_sleague.php
 * sleague.jp/schedule/ のHTMLを解析し、開催日順の統一スケジュール配列を作る。
 *
 * [PHASE 2で実HTML(view-source)を確認して確定した実DOM構造]
 *   <section class="schedule-section schedule-section--s1|s2|masters">
 *     <h2 class="schedule-section__heading">S.ONE ショートボード</h2>
 *     <div class="schedule-list">
 *       <a href="..." class="schedule-item"> ... </a>                (リンクあり大会)
 *       <div class="schedule-item schedule-item--no-link"> ... </div> (調整中など)
 *         <span class="schedule-item__battle">第2戦</span>
 *         <span class="schedule-item__name">...</span>
 *         <span class="schedule-item__date">...</span>
 *         <span class="schedule-item__venue">...</span>
 *         <span class="schedule-item__status">
 *           <span class="badge badge--done|badge--active|badge--upcoming">終了|開催中|開催予定</span>
 *         </span>
 *     </div>
 *   </section>
 *
 * badge--done → 終了 / badge--active → 開催中 / badge--upcoming → 開催予定
 * (旧実装は自然文からの正規表現推測だったが、実HTMLでは値が構造化されて
 *  取れることが確定したため、正規表現での日付・開催地の切り出しは不要になった。
 *  日付の暦日変換(start_date/end_date算出)のみ、date spanのテキストに対して行う)
 */

const LEAGUE_BOARD_MAP = [
    'S.ONE ショートボード' => ['league' => 'S.ONE', 'board' => 'SHORT'],
    'S.ONE ロングボード'   => ['league' => 'S.ONE', 'board' => 'LONG'],
    'S.TWO ショートボード' => ['league' => 'S.TWO', 'board' => 'SHORT'],
    'S.TWO ロングボード'   => ['league' => 'S.TWO', 'board' => 'LONG'],
    'MASTERS'              => ['league' => 'MASTERS', 'board' => null],
];

const BADGE_STATUS_MAP = [
    'badge--done' => '終了',
    'badge--active' => '開催中',
    'badge--upcoming' => '開催予定',
];

function parse_schedule_html(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $sections = $xpath->query("//section[contains(concat(' ',normalize-space(@class),' '),' schedule-section ')]");

    $events = [];
    $parseWarnings = [];

    foreach ($sections as $section) {
        $h2 = $xpath->query(".//h2[contains(concat(' ',normalize-space(@class),' '),' schedule-section__heading ')]", $section)->item(0);
        if (!$h2) {
            $parseWarnings[] = 'section without heading skipped';
            continue;
        }
        $sectionTitle = trim($h2->textContent);
        if (!isset(LEAGUE_BOARD_MAP[$sectionTitle])) {
            $parseWarnings[] = "unknown section heading: {$sectionTitle}";
            continue;
        }
        $leagueBoard = LEAGUE_BOARD_MAP[$sectionTitle];

        $items = $xpath->query(".//*[contains(concat(' ',normalize-space(@class),' '),' schedule-item ')]", $section);

        foreach ($items as $item) {
            $parsed = parse_schedule_item_node($item, $xpath);
            if ($parsed === null) {
                $parseWarnings[] = "unparsed schedule-item in [{$sectionTitle}]";
                continue;
            }
            $parsed['league'] = $leagueBoard['league'];
            $parsed['board'] = $leagueBoard['board'];

            $key = $parsed['url'] ?? ('noslug:' . $sectionTitle . ':' . $parsed['round'] . ':' . ($parsed['name'] ?? $parsed['raw_text']));

            $tag = $leagueBoard['league'] . ($leagueBoard['board'] ? "-{$leagueBoard['board']}" : '');
            if (isset($events[$key])) {
                $events[$key]['category_tags'][] = $tag;
                $events[$key]['category_tags'] = array_values(array_unique($events[$key]['category_tags']));
            } else {
                $parsed['category_tags'] = [$tag];
                $events[$key] = $parsed;
            }
        }
    }

    $list = array_values($events);

    usort($list, function ($a, $b) {
        $aHas = $a['start_date'] !== null;
        $bHas = $b['start_date'] !== null;
        if ($aHas && $bHas) return strcmp($a['start_date'], $b['start_date']);
        if ($aHas && !$bHas) return -1;
        if (!$aHas && $bHas) return 1;
        return 0;
    });

    return ['events' => $list, 'warnings' => $parseWarnings];
}

function parse_schedule_item_node(DOMElement $item, DOMXPath $xpath): ?array
{
    $get = function (string $cls) use ($item, $xpath): ?string {
        $node = $xpath->query(".//span[contains(concat(' ',normalize-space(@class),' '),' {$cls} ')]", $item)->item(0);
        if (!$node) return null;
        // textContentには " "(U+00A0 non-breaking space)が混入することがあるため、
        // 通常のtrim()では取れない。Unicode対応の空白除去で正規化する。
        return preg_replace('/^\s+|\s+$/u', '', str_replace("\xc2\xa0", ' ', $node->textContent));
    };

    $battle = $get('schedule-item__battle');
    $name = $get('schedule-item__name');
    $dateText = $get('schedule-item__date');
    $venue = $get('schedule-item__venue');

    $badge = $xpath->query(".//span[contains(concat(' ',normalize-space(@class),' '),' badge ')]", $item)->item(0);
    $statusRaw = $badge ? trim($badge->textContent) : null;

    if ($name === null || $dateText === null || $statusRaw === null) {
        return null; // 必須フィールドが取れないカードは異常として除外(推測で埋めない)
    }

    $href = ($item->nodeName === 'a') ? $item->attributes->getNamedItem('href')?->nodeValue : null;

    $startDate = null;
    $endDate = null;
    if (preg_match('/(\d{1,2})月(\d{1,2})日\([^)]+\)[〜～](\d{1,2})日\([^)]+\)/u', $dateText, $m)) {
        $month = (int) $m[1];
        $year = infer_year($month);
        $startDate = sprintf('%04d-%02d-%02d', $year, $month, (int) $m[2]);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, (int) $m[3]);
    } elseif (preg_match('/(\d{1,2})月(\d{1,2})日\([^)]+\)/u', $dateText, $m)) {
        $month = (int) $m[1];
        $year = infer_year($month);
        $startDate = sprintf('%04d-%02d-%02d', $year, $month, (int) $m[2]);
        $endDate = $startDate;
    }

    return [
        'round' => $battle,
        'name' => $name !== '' && $name !== null ? $name : null,
        'date_text' => $dateText,
        'date_confirmed' => $startDate !== null,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'venue' => $venue,
        'status_raw' => $statusRaw, // 公式バッジの表示テキストそのまま。推測ではなく公式ラベルを正とする。
        'url' => $href,
        'raw_text' => null, // 実DOMでは構造化取得できるため、raw_textフォールバックは基本不要
    ];
}

function infer_year(int $month, int $seasonStartYear = 2026): int
{
    return $month >= 7 ? $seasonStartYear : $seasonStartYear + 1;
}
