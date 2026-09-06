<?php
/**
 * view_helpers.php
 * SCHEDULE画面(および将来のHOME/EVENT)で共通して使うビュー用ヘルパー関数。
 * データ取得はしない(閲覧時に外部へアクセスしない=指示書2番)。ローカルJSONの読み込み・整形のみ。
 */

/**
 * data/schedule.json を読み込む。読めない/壊れている場合はnullを返す(画面側で空状態を出す)。
 */
function load_schedule_data(): ?array
{
    return load_json(DATA_DIR . '/schedule.json');
}

function normalize_schedule_league_code(string $league): string
{
    return match ($league) {
        'S1' => 'S.ONE',
        'S2' => 'S.TWO',
        default => $league,
    };
}

function is_schedule_main_round(array $round): bool
{
    return preg_match('/^(開幕戦|第\d+戦)$/u', $round['round']) === 1;
}

function get_ranking_category_schedule_rounds(array $scheduleData, string $categoryKey): array
{
    if (empty($scheduleData['items'])) {
        return [];
    }

    $defs = ranking_category_definitions();
    $selectedDef = $defs[$categoryKey] ?? null;
    if ($selectedDef === null) {
        return [];
    }

    $expectedLeague = normalize_schedule_league_code($selectedDef['league']);
    $rounds = [];
    foreach ($scheduleData['items'] as $event) {
        if (($event['league'] ?? '') !== $expectedLeague) {
            continue;
        }
        if ($selectedDef['board'] !== null) {
            if (($event['board'] ?? '') !== $selectedDef['board']) {
                continue;
            }
        } elseif (!empty($event['board'])) {
            continue;
        }

        $round = trim((string) ($event['round'] ?? ''));
        if ($round === '') {
            continue;
        }

        if (!isset($rounds[$round])) {
            $status = 'upcoming';
            $rawStatus = $event['status_raw'] ?? '';
            if ($rawStatus === '開催中') {
                $status = 'live';
            } elseif ($rawStatus === '終了') {
                $status = 'finished';
            }
            $rounds[$round] = [
                'round' => $round,
                'status' => $status,
                'events' => [$event],
                'start_date' => $event['start_date'] ?? null,
            ];
            continue;
        }

        $rounds[$round]['events'][] = $event;
        $rawStatus = $event['status_raw'] ?? '';
        if ($rawStatus === '開催中') {
            $rounds[$round]['status'] = 'live';
        } elseif ($rounds[$round]['status'] !== 'live' && $rawStatus === '終了') {
            $rounds[$round]['status'] = 'finished';
        }
    }

    return array_values($rounds);
}

function get_ranking_category_schedule_main_rounds(array $rounds): array
{
    $mainRounds = array_values(array_filter($rounds, fn(array $round) => is_schedule_main_round($round)));
    if ($mainRounds === []) {
        return $rounds;
    }

    usort($mainRounds, function (array $a, array $b): int {
        if ($a['round'] === '開幕戦') {
            return -1;
        }
        if ($b['round'] === '開幕戦') {
            return 1;
        }
        return strcmp($a['round'], $b['round']);
    });

    return $mainRounds;
}

function get_ranking_category_schedule_summary(array $scheduleData, string $categoryKey): array
{
    $rounds = get_ranking_category_schedule_rounds($scheduleData, $categoryKey);
    if (empty($rounds)) {
        return [];
    }

    $mainRounds = get_ranking_category_schedule_main_rounds($rounds);
    $extraRounds = array_values(array_filter($rounds, fn(array $round) => !is_schedule_main_round($round)));

    $mainCount = count($mainRounds);
    if ($mainCount === 0) {
        $mainCount = count($rounds);
    }

    $finishedCount = count(array_filter($mainRounds, fn(array $round) => $round['status'] === 'finished'));
    $liveRound = array_filter($mainRounds, fn(array $round) => $round['status'] === 'live');

    if (!empty($liveRound)) {
        $currentLabel = ($liveRound[0]['round'] === '開幕戦' ? '第1戦' : $liveRound[0]['round']) . '開催中';
    } elseif ($finishedCount > 0) {
        $currentLabel = '第' . $finishedCount . '戦終了';
    } else {
        $currentLabel = '第0戦終了';
    }

    $totalLabel = '全' . $mainCount . '戦';

    return [
        'rounds' => array_map(fn(array $round) => [
            'round' => $round['round'] === '開幕戦' ? '第1戦' : $round['round'],
            'status' => $round['status'],
        ], $mainRounds),
        'current_label' => '現在：' . $currentLabel,
        'total_label' => $totalLabel,
    ];
}

/**
 * 確定日イベントを年→月→イベント配列にグルーピングする(開催日順は呼び出し前提のitems順を維持)。
 * 未確定日(調整中)イベントは別途返す。
 *
 * @return array{by_year: array, tbd: array}
 */
function group_events_for_schedule(array $items): array
{
    $byYear = [];
    $tbd = [];

    foreach ($items as $e) {
        if (empty($e['date_confirmed']) || empty($e['start_date'])) {
            $tbd[] = $e;
            continue;
        }
        $dt = new DateTimeImmutable($e['start_date']);
        $year = $dt->format('Y');
        $month = $dt->format('n'); // 1-12
        $byYear[$year][$month][] = $e;
    }

    // 年・月ともに昇順を保証(itemsは既にstart_date順ソート済みだが、年月キーの並び順も明示しておく)
    ksort($byYear);
    foreach ($byYear as &$months) {
        ksort($months);
    }
    unset($months);

    return ['by_year' => $byYear, 'tbd' => $tbd];
}

const MONTH_LABELS_EN = [
    1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN',
    7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC',
];

/**
 * "AUG 07-09" / "AUG 07"(単日) のような表示用日付文字列を作る。
 */
function format_event_date_range(array $event): string
{
    if (empty($event['date_confirmed']) || empty($event['start_date'])) {
        return 'DATE TBD';
    }
    $start = new DateTimeImmutable($event['start_date']);
    $end = !empty($event['end_date']) ? new DateTimeImmutable($event['end_date']) : $start;

    $month = MONTH_LABELS_EN[(int) $start->format('n')];

    if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
        return sprintf('%s %s', $month, $start->format('d'));
    }
    if ($start->format('n') === $end->format('n')) {
        return sprintf('%s %s-%s', $month, $start->format('d'), $end->format('d'));
    }
    // 月をまたぐ場合
    return sprintf('%s %s - %s %s', $month, $start->format('d'), MONTH_LABELS_EN[(int) $end->format('n')], $end->format('d'));
}

/**
 * status_raw("開催中"/"終了"/"開催予定")をCSS変数のキーへ変換する。
 * 表示テキストはstatus_rawそのままを使う(指示書5番)。ここでは色分け用クラス名のみ決める。
 */
function status_css_class(?string $statusRaw): string
{
    return match ($statusRaw) {
        '開催中' => 'status-live',
        '終了' => 'status-finished',
        '開催予定' => 'status-upcoming',
        default => 'status-unknown',
    };
}

/**
 * league/board から表示用タグ配列を作る。例: ["S.ONE", "SHORT"] / ["MASTERS"]
 */
function event_tags(array $event): array
{
    $tags = [];
    if (!empty($event['league'])) {
        $tags[] = $event['league'];
    }
    if (!empty($event['board'])) {
        $tags[] = $event['board'];
    }
    return $tags;
}

/**
 * 最終更新表示用に、schedule.jsonのfetched_atを "2026.08.08 21:00" 形式へ整形する。
 */
function format_last_updated(?string $isoDateTime): string
{
    if ($isoDateTime === null) {
        return '取得情報なし';
    }
    try {
        $dt = new DateTimeImmutable($isoDateTime);
        return $dt->format('Y.m.d H:i');
    } catch (Exception) {
        return '取得情報なし';
    }
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/* ==========================================================================
   RANKING画面用ヘルパー
   HOME(将来のTOP5表示)とRANKING画面(全件)の両方から共通利用できるように、
   「ranking.jsonを読む」「カテゴリキーを解決する」「指定カテゴリの行を返す」
   を独立した小さい関数に分けている。
   ========================================================================== */

/**
 * data/ranking.json を読み込む。読めない/壊れている場合はnull。
 */
function load_ranking_data(): ?array
{
    return load_json(DATA_DIR . '/ranking.json');
}

/**
 * 9カテゴリの定義(表示順・表示ラベル・ranking.json側のキー組み立てルール)。
 * ranking.json側のキーは parser_ranking_sleague.php が
 * "S1:SHORT:MEN" のように league:board:div (MASTERSはboard/divなし) で生成している。
 */
function ranking_category_definitions(): array
{
    $leagues = [
        ['code' => 'S1', 'label' => 'S.ONE'],
        ['code' => 'S2', 'label' => 'S.TWO'],
    ];
    $boards = [
        ['code' => 'SHORT', 'label' => 'ショートボード'],
        ['code' => 'LONG', 'label' => 'ロングボード'],
    ];
    $divs = [
        ['code' => 'MEN', 'label' => 'メンズ'],
        ['code' => 'WOMEN', 'label' => 'ウィメンズ'],
    ];

    $defs = [];
    foreach ($leagues as $l) {
        foreach ($boards as $b) {
            foreach ($divs as $d) {
                $key = "{$l['code']}:{$b['code']}:{$d['code']}";
                $defs[$key] = [
                    'key' => $key,
                    'league' => $l['code'], 'league_label' => $l['label'],
                    'board' => $b['code'], 'board_label' => $b['label'],
                    'div' => $d['code'], 'div_label' => $d['label'],
                    'title' => "{$l['label']} {$b['label']} {$d['label']}",
                ];
            }
        }
    }
    // MASTERSはboard/div区分が無い単独カテゴリ
    $defs['MASTERS'] = [
        'key' => 'MASTERS',
        'league' => 'MASTERS', 'league_label' => 'MASTERS',
        'board' => null, 'board_label' => null,
        'div' => null, 'div_label' => null,
        'title' => 'MASTERS',
    ];

    return $defs;
}

const RANKING_DEFAULT_CATEGORY = 'S1:SHORT:MEN';

/**
 * GETパラメータ(league/board/div)から、実際に存在する(ranking.json内にデータがある)
 * カテゴリキーを安全に決定する。存在しない/不正な組み合わせならデフォルトへフォールバックする。
 */
function resolve_ranking_category(array $queryParams, array $availableKeys): string
{
    $league = strtoupper((string) ($queryParams['league'] ?? ''));
    $board = strtoupper((string) ($queryParams['board'] ?? ''));
    $div = strtoupper((string) ($queryParams['div'] ?? ''));

    if ($league === 'MASTERS') {
        return in_array('MASTERS', $availableKeys, true) ? 'MASTERS' : RANKING_DEFAULT_CATEGORY;
    }

    if (in_array($league, ['S1', 'S2'], true) && in_array($board, ['SHORT', 'LONG'], true) && in_array($div, ['MEN', 'WOMEN'], true)) {
        $key = "{$league}:{$board}:{$div}";
        if (in_array($key, $availableKeys, true)) {
            return $key;
        }
    }

    return in_array(RANKING_DEFAULT_CATEGORY, $availableKeys, true) ? RANKING_DEFAULT_CATEGORY : ($availableKeys[0] ?? RANKING_DEFAULT_CATEGORY);
}

/**
 * 指定カテゴリの全行を返す(存在しなければ空配列)。同順位は入力順のまま保持し、
 * 呼び出し側で再ソート・重複排除・グルーピングをしないこと(同順位を壊さないため)。
 */
function get_ranking_rows(array $rankingItems, string $key): array
{
    return $rankingItems[$key] ?? [];
}

/**
 * 指定カテゴリの上位N件を返す(HOME TOP5等での再利用を想定)。
 * ties(同順位)がN件目をまたぐ場合でも、単純に配列の先頭N件を返す
 * (公式サイトのTOP表示も同じ挙動のため、それに合わせる)。
 */
function normalize_ranking_round_points_with_schedule(array $rankingItems, ?array $scheduleData): array
{
    if (empty($scheduleData['items'])) {
        return $rankingItems;
    }

    $normalized = [];
    foreach ($rankingItems as $categoryKey => $rows) {
        $scheduleRounds = get_ranking_category_schedule_rounds($scheduleData, $categoryKey);
        $mainRounds = get_ranking_category_schedule_main_rounds($scheduleRounds);
        $scheduleCount = count($mainRounds);

        foreach ($rows as $rowIndex => $row) {
            $points = $row['round_points'] ?? [];
            $maxCount = max($scheduleCount, count($points));
            $normalizedPoints = [];

            for ($i = 0; $i < $maxCount; $i++) {
                $value = array_key_exists($i, $points) ? $points[$i] : null;
                if ($value === 0 && isset($mainRounds[$i]) && $mainRounds[$i]['status'] !== 'finished') {
                    $normalizedPoints[$i] = null;
                } else {
                    $normalizedPoints[$i] = $value;
                }
            }

            $row['round_points'] = $normalizedPoints;
            $normalized[$categoryKey][$rowIndex] = $row;
        }
    }

    return $normalized;
}

function get_ranking_top(array $rankingItems, string $key, int $n = 5): array
{
    return array_slice(get_ranking_rows($rankingItems, $key), 0, $n);
}

/**
 * 選手名の表示文字列を作る。ENABLE_PLAYER_NAMES=falseの場合は
 * 実名を一切出さず、行の位置(1始まり)に基づく匿名ラベルへ置き換える。
 */
function ranking_display_name(array $row, int $rowIndex): string
{
    if (!ENABLE_PLAYER_NAMES) {
        return sprintf('選手%02d', $rowIndex + 1);
    }
    $name = $row['name'] ?? '';
    if (!empty($row['annotation'])) {
        $name .= '(' . $row['annotation'] . ')';
    }
    return $name;
}

/* ==========================================================================
   全画面共通: 下部固定ナビゲーション
   HOME/RANKING/SCHEDULEの3画面すべてから呼ぶ。JavaScriptは使用しない。
   ========================================================================== */

function render_header_nav(string $active): void
{
    $items = [
        ['key' => 'home', 'label' => 'HOME', 'href' => 'index.php'],
        ['key' => 'ranking', 'label' => 'RANKING', 'href' => 'ranking.php'],
        ['key' => 'schedule', 'label' => 'SCHEDULE', 'href' => 'schedule.php'],
    ];
    echo '<nav class="header-nav" aria-label="グローバルナビゲーション">';
    foreach ($items as $item) {
        $isActive = $item['key'] === $active;
        printf(
            '<a class="header-nav__link%s" href="%s"%s>%s</a>',
            $isActive ? ' is-active' : '',
            h($item['href']),
            $isActive ? ' aria-current="page"' : '',
            h($item['label'])
        );
    }
    echo '</nav>';
}

function render_bottom_nav(string $active): void
{
    // footer fixed nav removed in favor of header navigation
}

/* ==========================================================================
   HOME専用: 表示上のみの同一大会グルーピング
   ========================================================================== */

/**
 * 「大会名・start_date・end_date・venue」の4項目が完全一致する場合に限り、
 * HOMEの表示上だけイベントを1カードへグループ化する。
 *
 * 重要: schedule.json自体は一切書き換えない。この関数はビュー層の一時的な
 * 集約であり、元データ(各イベントのURL・league・board等)はグループ内の
 * 'members' に個別のまま保持する。曖昧な文字列類似(部分一致・表記ゆれ吸収)
 * では絶対に統合しない — 4項目が1バイトでも違えば別カードのままにする。
 *
 * @return array 各要素は元のイベント配列に 'members'(グループを構成する元イベント配列のリスト)
 *               'grouped_leagues'(重複排除したリーグコード配列)を追加したもの。
 */
function group_events_for_home(array $events): array
{
    $groups = [];

    foreach ($events as $e) {
        // 日付未確定(調整中)のイベントは、HOME側では現状グルーピング対象にしない
        // (UPCOMING/NEXTは元々date_confirmed=trueのみを扱っているため通常ここには来ない)。
        $key = implode('◆', [
            $e['name'] ?? '',
            $e['start_date'] ?? '',
            $e['end_date'] ?? '',
            $e['venue'] ?? '',
        ]);

        if (isset($groups[$key])) {
            $groups[$key]['members'][] = $e;
            if (!empty($e['league'])) {
                $groups[$key]['grouped_leagues'][] = $e['league'];
            }
            if (!empty($e['board'])) {
                $groups[$key]['grouped_boards'][] = $e['board'];
            }
        } else {
            $merged = $e;
            $merged['members'] = [$e];
            $merged['grouped_leagues'] = !empty($e['league']) ? [$e['league']] : [];
            $merged['grouped_boards'] = !empty($e['board']) ? [$e['board']] : [];
            $groups[$key] = $merged;
        }
    }

    foreach ($groups as &$g) {
        $g['grouped_leagues'] = array_values(array_unique($g['grouped_leagues']));
        $g['grouped_boards'] = array_values(array_unique($g['grouped_boards']));
    }
    unset($g);

    return array_values($groups);
}

/**
 * グループ化済みイベントの表示用タグ配列を作る。
 * league・boardの両方について、メンバー間で異なる値があれば全て列挙する
 * (例: 同一大会がS.TWO SHORTとS.TWO LONGで別イベントとして存在するケースがある。
 *  「先に見つかった方のboardだけ表示」は情報の取りこぼしになるため、必ず全メンバーの
 *  league/boardをマージしてから表示する)。
 */
function grouped_event_tags(array $groupedEvent): array
{
    $leagues = $groupedEvent['grouped_leagues'] ?? [];
    $boards = $groupedEvent['grouped_boards'] ?? [];
    return array_merge($leagues, $boards);
}

/**
 * グループ化済みイベントに、安全にリンクしてよい単一の公式URLがあれば返す。
 * メンバーが2件以上の異なる非nullURLを持つ場合は、代表URLを勝手に決めず null を返す
 * (指示書: 複数公式URLがある場合に片方を代表として選ばない)。
 */
function grouped_event_single_url(array $groupedEvent): ?string
{
    $urls = array_values(array_unique(array_filter(array_map(
        fn($m) => $m['url'] ?? null,
        $groupedEvent['members'] ?? [$groupedEvent]
    ))));
    return count($urls) === 1 ? $urls[0] : null;
}

/* ==========================================================================
   EVENTページ用: 安定したevent_idの生成とルックアップ
   ========================================================================== */

/**
 * schedule.json内の1イベントから、安定したevent_idを生成する。
 * 表示名(大会名)だけをキーにしない(同名大会・S.ONE/S.TWO別ページが存在するため)。
 * - 公式URLがあれば、そのURLの末尾スラッグをそのまま使う(最も安定・衝突しにくい)。
 * - URLが無い(調整中で個別ページ未発行)場合のみ、league/board/round/nameから
 *   決定的なハッシュを生成する(内容が変わらない限り再取得しても同じIDになる)。
 */
function event_id_for(array $event): string
{
    if (!empty($event['url'])) {
        $slug = basename(rtrim($event['url'], '/'));
        if ($slug !== '') {
            return $slug;
        }
    }
    $seed = implode('|', [$event['league'] ?? '', $event['board'] ?? '', $event['round'] ?? '', $event['name'] ?? '']);
    return 'tbd-' . substr(md5($seed), 0, 10);
}

/**
 * 指定event_idを持つイベントの「グループ」(HOMEと同じ、大会名/start_date/end_date/venue
 * 完全一致によるまとめ)を、schedule.json全アイテム(確定日・調整中を問わず)から探す。
 * 見つからなければnull(呼び出し側で404にする)。
 */
function find_event_group_by_id(array $allItems, string $id): ?array
{
    $groups = group_events_for_home($allItems);
    foreach ($groups as $g) {
        foreach ($g['members'] as $m) {
            if (event_id_for($m) === $id) {
                return $g;
            }
        }
    }
    return null;
}

/**
 * date_textから「予備日」の表記だけを安全に抜き出す。取れなければnull(推測しない)。
 * 例: "8月7日(金)〜9日(日)　予備日:10日(月)" → "10日(月)"
 */
function extract_reserve_day(?string $dateText): ?string
{
    if ($dateText === null) {
        return null;
    }
    if (preg_match('/予備日[:：]\s*([^\s　]+)/u', $dateText, $m)) {
        return $m[1];
    }
    return null;
}
