<?php
/**
 * seo_config.php
 * SEO関連の文言(title断片・meta description等)を一元管理する。
 * 実際にHTMLへ出力する処理は seo_helper.php 側に置き、ここには文字列だけを置く。
 * 将来S.LEAGUE/JPSAとの正式名称協議の結果が出ても、ここだけ直せば全ページへ反映される。
 */

// ---- HOME ----
const SEO_HOME_TITLE_SUFFIX = '｜Sリーグ サーフィン大会・ランキング・日程';
const SEO_HOME_DESCRIPTION = 'S.LEAGUEの開催中大会、次回大会、S.ONE・S.TWO・MASTERSのランキング、2026-27シーズンの日程を見やすく確認できる非公式サーフィン情報ポータル。';

// ---- RANKING ----
const SEO_RANKING_TITLE_BASE = 'S.LEAGUE ランキング｜S.ONE・S.TWO・MASTERS 最新順位';
const SEO_RANKING_TITLE_CATEGORY_SUFFIX = 'ランキング';
const SEO_RANKING_DESCRIPTION = 'S.LEAGUE(S.ONE・S.TWO・MASTERS)のショートボード・ロングボード、メンズ・ウィメンズ全カテゴリの最新ランキングを確認できます。';

// ---- SCHEDULE ----
const SEO_SCHEDULE_TITLE = 'S.LEAGUE 大会日程｜2026-27 サーフィンスケジュール';
const SEO_SCHEDULE_DESCRIPTION = 'S.LEAGUE 2026-27シーズンの全大会日程を開催日順に一本化して確認できます。開催中・開催予定のステータスも一目で分かります。';

// ---- EVENT ----
const SEO_EVENT_TITLE_SUFFIX = '｜日程・会場';
