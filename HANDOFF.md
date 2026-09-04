# S.LEAGUE NOW — HANDOFF

基準日: 2026-09-04 JST

このファイルは、直近チャットの内容を会話ログではなく「現在状態・確定仕様・次の再開点」に圧縮して保存したものです。

## 現在の完成・正常確認済み

- ローカルPHP環境構築済み
- 起動: `php -S localhost:8000 local-server.php`
- SSL/CA設定済み
- `fetch_all.php` 正常
  - schedule: 20 events
  - ranking: 390 rows
- 本番cron: 毎時30分
- ランキング
  - 各戦ポイント表示
  - 未開催は `—`
  - 開催済み0点は `0`
  - 合計表示
  - `現在：第1戦終了 / 全4戦`
  - `SEASON PROGRESS`
- 本番ランキング正常表示を確認済み

## ヘッダーナビ確定仕様

`HOME | RANKING | SCHEDULE`

- ページ最上部
- 隙間なし3分割
- 高さ約60〜64px
- 全面クリック可能
- 現在ページはシアン
- PC/スマホ共通
- フッター固定ナビは削除
- お問い合わせは後回し

## schedule.php の安定版ルール

イベントカードは元の正常レイアウトを基準にする。

- scheduleカード構造を不用意に変更しない
- event-card系CSSを不用意に変更しない
- 「詳細を見る 〉〉」改善は後回し

注意書きの確定方針:
- 上部の大きな注意喚起ボックスは削除
- 上部の公式サイトボタンは削除
- 下部の既存注意書きは残す
- 文中の「S.LEAGUE公式サイト」だけ `https://sleague.jp/` へリンク
- 新しいボタンは作らない
- scheduleカードは触らない

## SEO / 公開状態

SEO基本実装:
- title
- meta description
- canonical
- OGP
- Twitter Card
- EVENTページの動的title / description / canonical
- EVENT構造化データは確定日付・start_date・nameが揃う場合のみ出力

現在の公開設定:
- `ENABLE_PUBLIC_INDEXING = true`
- noindexは解除済み
- Google Search Console 所有権確認済み
- 本番URL: `https://sleague.rss7.net/`

## CSS / 本番反映で起きたこと

上部ナビ反映時、本番とローカルの共通ファイル差分により一時的に本文が真っ黒になる事象が発生。

確認・対応した主な対象:
- `public/index.php`
- `public/ranking.php`
- `public/schedule.php`
- `public/event.php`
- `inc/view_helpers.php`
- `inc/seo_helper.php`
- `assets/css/site.css`

最終的にEdgeでは上部3分割ナビを正常表示できた。Chrome側ではキャッシュの影響が疑われた。

`site.css` には以下のナビCSSが存在する:
- `.header-nav`
- `.header-nav__link`
- `.header-nav__link.is-active`

今後CSS反映が不安定な場合は、CSS URLへバージョンqueryを付ける運用を検討する。

## Git

ローカルGit導入済み。

基準コミット:
- `9b5367f`
- `Baseline: stable S.LEAGUE NOW before next development`

誤って `workspaceStorage` に作成した `.git` は削除済み。
正しいGitはS.LEAGUE NOWプロジェクト直下。

注意:
- このGitHub Repositoryへのローカルソース本体の初回pushは、GitHub側で未確認のまま。
- 現在GitHubにはProject bootstrap文書のみ存在するため、ローカル実装ファイルを記憶から再構築しないこと。
- 次回はローカル側のGit実体を確認し、現在のソースをそのまま安全に初回pushする。

## 本番反映

現状はFileZillaによる手動アップロード。

自動デプロイはまだ導入しない。
本番反映時はローカルで正常確認後、変更対象を絞ってアップロードする。

## 次にやること

1. ローカルGitを `oosaka0123-sudo/sleague-now` の `main` に安全に接続・初回push
2. GitHub上で実装ファイルと履歴を確認
3. 必要ならREADMEを現実の構成に合わせて更新
4. お問い合わせページ作成はその後
5. SEO追加強化は必要時のみ

## 絶対に壊さない

- scheduleカードの正常レイアウト
- event-card系CSS
- rankingテーブル構造・表示
- 各戦ポイント
- SEASON PROGRESS
- fetch/parser/cron
- ヘッダーナビの確定デザイン

## 再開ルール

次回はチャット履歴ではなく、まず以下を確認する。

1. `oosaka0123-sudo/ai-master`
2. このRepositoryの `README.md`
3. `AGENTS.md`
4. `HANDOFF.md`
5. GitHubのcurrent code / Issue / PR / Actions / Commit

GitHubの実態と文書が違う場合は、GitHubの実態を現在状態として扱う。
