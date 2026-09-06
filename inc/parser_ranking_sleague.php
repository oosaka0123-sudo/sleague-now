<?php
/**
 * parser_ranking_sleague.php
 * sleague.jp/ranking/ のHTMLを解析し、league×board×division ごとのランキング配列を作る。
 *
 * [PHASE 2で実HTML(view-source)を確認して確定した実DOM構造]
 *   <div data-league-panel="s1|s2|masters">
 *     <div data-tab-panel="shortboard|longboard">        ← MASTERSにはこの階層が無い(タブなし)
 *       <div data-gender-panel="mens-xxx|womens-xxx">     ← MASTERSにはこの階層も無い(男女タブなし)
 *         <div class="ranking-table" style="--round-count: N">
 *           <div class="ranking-table__row">
 *             <span class="ranking-table__rank">1</span>
 *             <div class="ranking-table__avatar"><img ...></div>
 *             <span class="ranking-table__name">松永　大輝</span>
 *             <span class="ranking-table__point">5,000</span> ×(各戦)
 *             <span class="ranking-table__point ranking-table__point--total">5,000</span>
 *
 * 全リーグ・全カテゴリが1回のページ読み込みで同一DOM内に存在する(非表示のhidden属性で
 * 出し分けているだけ)。そのためカテゴリ別に個別URLを叩く必要はない。
 *
 * 写真(<img>のsrc)はENABLE_PHOTOS=falseの間、取得・保存しない。
 */

function parse_point_value(?string $text): ?int
{
    if ($text === null) {
        return null;
    }
    $normalized = trim(str_replace("\xc2\xa0", ' ', $text));
    if ($normalized === '' || $normalized === '-' || $normalized === '—') {
        return null;
    }
    $normalized = str_replace([',', ' '], '', $normalized);
    return preg_match('/^-?\d+$/', $normalized) ? (int) $normalized : null;
}

function parse_ranking_html(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $tables = $xpath->query("//div[contains(concat(' ',normalize-space(@class),' '),' ranking-table ')]");

    $result = [];
    $warnings = [];

    foreach ($tables as $table) {
        $leaguePanel = $xpath->query("ancestor::div[@data-league-panel][1]", $table)->item(0);
        $tabPanel = $xpath->query("ancestor::div[@data-tab-panel][1]", $table)->item(0);
        $genderPanel = $xpath->query("ancestor::div[@data-gender-panel][1]", $table)->item(0);

        if (!$leaguePanel) {
            $warnings[] = 'ranking-table found without ancestor data-league-panel; skipped';
            continue;
        }
        $league = strtoupper($leaguePanel->attributes->getNamedItem('data-league-panel')->nodeValue);

        $board = null;
        if ($tabPanel) {
            $rawBoard = $tabPanel->attributes->getNamedItem('data-tab-panel')->nodeValue;
            $board = str_contains($rawBoard, 'long') ? 'LONG' : (str_contains($rawBoard, 'short') ? 'SHORT' : strtoupper($rawBoard));
        }

        $div = null;
        if ($genderPanel) {
            $rawGender = $genderPanel->attributes->getNamedItem('data-gender-panel')->nodeValue;
            $div = str_starts_with($rawGender, 'womens') ? 'WOMEN' : (str_starts_with($rawGender, 'mens') ? 'MEN' : strtoupper($rawGender));
        }

        $key = implode(':', array_filter([$league, $board, $div]));

        $rows = $xpath->query(".//div[contains(concat(' ',normalize-space(@class),' '),' ranking-table__row ')]", $table);
        $list = [];
        foreach ($rows as $row) {
            $rankNode = $xpath->query(".//span[contains(concat(' ',normalize-space(@class),' '),' ranking-table__rank ')]", $row)->item(0);
            $nameNode = $xpath->query(".//span[contains(concat(' ',normalize-space(@class),' '),' ranking-table__name ')]", $row)->item(0);
            $totalNode = $xpath->query(".//span[contains(concat(' ',normalize-space(@class),' '),' ranking-table__point--total ')]", $row)->item(0);

            if (!$rankNode || !$nameNode || !$totalNode) {
                $warnings[] = "ranking row missing rank/name/total in [{$key}]";
                continue;
            }

            $rawName = trim($nameNode->textContent);
            $playerName = $rawName;
            $annotation = null;
            // 選手名末尾の "(S.TWO)" 等の注記のみ、半角括弧終端パターンで安全に分離する。
            if (preg_match('/^(.*?)[\s]*\(([^()]+)\)$/u', $rawName, $m)) {
                $playerName = trim($m[1]);
                $annotation = trim($m[2]);
            }

            $points = (int) str_replace(',', '', trim($totalNode->textContent));

            $pointNodes = $xpath->query(".//span[contains(concat(' ',normalize-space(@class),' '),' ranking-table__point ')]", $row);
            $roundPoints = [];
            foreach ($pointNodes as $pointNode) {
                $classAttr = $pointNode->attributes->getNamedItem('class')->nodeValue ?? '';
                if (str_contains($classAttr, 'ranking-table__point--total')) {
                    continue;
                }
                $roundPoints[] = parse_point_value($pointNode->textContent);
            }

            $list[] = [
                'rank' => (int) trim($rankNode->textContent),
                'name' => $playerName,
                'annotation' => $annotation,
                'points' => $points,
                'round_points' => $roundPoints,
                // ENABLE_PHOTOS=falseの間は写真URLを保存しない(指示書の方針)
            ];
        }

        if (!empty($list)) {
            // 同じ$keyが複数箇所から来ることは無い想定(1カテゴリ1テーブル)だが、
            // 念のためあれば上書きせず警告する。
            if (isset($result[$key])) {
                $warnings[] = "duplicate ranking key encountered: {$key}";
            }
            $result[$key] = $list;
        }
    }

    return ['ranking' => $result, 'warnings' => $warnings];
}
