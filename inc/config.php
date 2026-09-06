<?php
/**
 * config.php
 * S.LEAGUE非公式ポータル 共通設定
 *
 * 著作権・利用規約の確認が取れるまでは、JPSA由来データ(公式コール転載・
 * JPSAへの直接リンク・LiveScore埋め込み・選手写真)は全てOFFにしておく。
 * ここのフラグをtrueにするのは、JPSAへの確認が完了してから。
 */

// ---- デモサイトである旨(正式公開時にここをfalseへ) ----
define('IS_DEMO_MODE', false); // true間は Basic認証 / noindex / X-Robots-Tag / デモ表記 が全ページに強制される

// ---- サイト名・キャッチコピー(正式名称確定前なので一括変更できるようにする) ----
define('SITE_NAME', 'S.LEAGUE NOW');
define('SITE_TAGLINE', 'S.LEAGUEの"今"が3秒でわかる。');

// ---- 正式公開時のURL基盤(未確定の間は空文字のままにしておく) ----
// BASE_URLが空の間はcanonical/OGPのurlを出力しない。
define('BASE_URL', 'https://sleague.rss7.net');

// ---- 検索エンジンへの公開可否(安全弁) ----
// IS_DEMO_MODE=false にしただけでは検索公開されない。
// 正式公開は IS_DEMO_MODE=false と ENABLE_PUBLIC_INDEXING=true の両方が揃った時だけ.
define('ENABLE_PUBLIC_INDEXING', true);

// ---- 機能フラグ ----
// S.LEAGUE/JPSAへの確認結果が出るまでは、JPSA由来・転載性の高いものは全てfalseで開始する。
// 各フラグは「サイト全体からその要素だけを一括で消す」ためのものであり、
// ページ側の実装はこのフラグを見て出し分けるだけにし、ページごとの個別修正は行わないこと。
define('ENABLE_SCHEDULE', true);          // 大会日程・CURRENT/NEXT EVENT (SOURCE_SLEAGUE)
define('ENABLE_RANKING', true);           // ランキングの枠組み自体(順位表示)  (SOURCE_SLEAGUE)
define('ENABLE_PLAYER_NAMES', true);      // ランキング内の選手名表示 (SOURCE_SLEAGUE) ※OFF時は「選手A」等匿名表示にする
define('ENABLE_POINTS', true);            // ランキング内のポイント表示 (SOURCE_SLEAGUE)
define('ENABLE_OFFICIAL', false);         // OFFICIAL CALL(日付+タイトル+公式リンクのみ) (SOURCE_JPSA)
define('ENABLE_LIVESCORE', false);        // LiveScoreへのリンク/iframe (SOURCE_JPSA_LIVE)
define('ENABLE_YOUTUBE', true);           // YouTubeへのリンク/埋め込み (SOURCE_YOUTUBE)
define('ENABLE_EXTERNAL_LINKS', true);    // S.LEAGUE公式ページへの導線リンク (SOURCE_SLEAGUE)
define('ENABLE_JPSA_LINKS', false);       // JPSA公式ページへの導線リンク (SOURCE_JPSA)
define('ENABLE_LOGOS', false);            // 公式ロゴ画像の使用 (将来許諾が出るまでfalse)
define('ENABLE_PHOTOS', false);           // 選手写真・大会写真の使用 (将来許諾が出るまでfalse)

// ---- データソース識別子(将来「JPSAだけ停止」等を機能フラグと組み合わせて判定するために使う) ----
define('SOURCE_SLEAGUE', 'SOURCE_SLEAGUE');
define('SOURCE_JPSA', 'SOURCE_JPSA');
define('SOURCE_JPSA_LIVE', 'SOURCE_JPSA_LIVE');
define('SOURCE_YOUTUBE', 'SOURCE_YOUTUBE');

// ---- 取得元URL ----
define('URL_SLEAGUE_SCHEDULE', 'https://sleague.jp/schedule/');
define('URL_SLEAGUE_RANKING', 'https://sleague.jp/ranking/');
define('URL_JPSA_OFFICIAL_CALL', 'https://www.jpsa.com/official_call/');

// ---- YouTube ----
define('YOUTUBE_CHANNEL_ID',     'UCLMnjEjefv0DfKQNuyZyumQ');
define('YOUTUBE_CHANNEL_HANDLE', '@SLEAGUE_OFFICIAL');
define('YOUTUBE_CHANNEL_URL',    'https://www.youtube.com/@SLEAGUE_OFFICIAL');
define('YOUTUBE_RSS_URL',        'https://www.youtube.com/feeds/videos.xml?channel_id=UCLMnjEjefv0DfKQNuyZyumQ');

// ---- パス ----
define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');
define('LOG_DIR', BASE_DIR . '/logs');

// ---- HTTPクライアント設定 ----
define('HTTP_TIMEOUT_SEC', 10);
define('HTTP_USER_AGENT', 'KansaiSurferKS-SLeaguePortal/0.1 (+non-commercial fan portal; contact: kansai.rss7.net)');

// ---- 保存時の最低件数バリデーション(これ未満なら「取得失敗」として旧データ保持) ----
define('MIN_SCHEDULE_EVENTS', 5);   // 5系統×数戦あるはずなので極端に少なければ異常とみなす
define('MIN_RANKING_CATEGORIES', 3); // ranking.jsonのカテゴリ数(通常9)がこれ未満なら異常とみなす

// ---- ログローテーション ----
define('LOG_MAX_LINES', 2000); // これを超えたら古い行から切り詰める

/**
 * データソース単位での一括停止判定。
 * 例: 「JPSA情報は不可」となった場合、is_source_enabled(SOURCE_JPSA) が false を返すようにする。
 * ページ側は個別修正せず、この関数とENABLE_*フラグの組み合わせだけを見て表示/非表示を切り替える。
 */
function is_source_enabled(string $source): bool
{
    switch ($source) {
        case SOURCE_SLEAGUE:
            return true; // S.LEAGUE自体は本サイトの本体機能なので常時true
        case SOURCE_JPSA:
            return ENABLE_OFFICIAL || ENABLE_JPSA_LINKS;
        case SOURCE_JPSA_LIVE:
            return ENABLE_LIVESCORE;
        case SOURCE_YOUTUBE:
            return ENABLE_YOUTUBE;
        default:
            return false;
    }
}

// ---- デモ表記(正式公開時にIS_DEMO_MODEをfalseにすればこの文言も自動的に非表示になる想定) ----
define('DEMO_BANNER_TEXT', 'S.LEAGUE/JPSA確認用デモサイト ｜ 一般公開前 ｜ 非公式 ｜ 非営利で制作中');
