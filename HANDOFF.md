# S.LEAGUE NOW — HANDOFF

基準日: 2026-09-06 JST

このファイルは、次のAI/SessionがGitHubから安全に再開するための「現在状態・確定仕様・再開点」です。会話ログは保存しません。

## 現在の正本

- Project Repository: `oosaka0123-sudo/sleague-now`
- default branch: `main`
- Master governance: `oosaka0123-sudo/ai-master`
- 本番URL: `https://sleague.rss7.net/`
- Project固有の現在状態はGitHubのcurrent code / Issue / PR / Commit / Actionsを優先する

## 初回ソースImport完了

2026-09-06、サーバーから取得した現行ZIPを確認・整理し、GitHub管理対象だけをImportした。

- PR #5 `import: stage verified current S.LEAGUE NOW source`
- squash merge済み
- merge SHA: `8731bb3fb212ad8de5e8b9d236960cfc8b6c6e9a`

`main` の主要領域:
- `public/`
- `inc/`
- `cron/`
- `assets/`
- `data/`（保護用ファイルのみ）
- `logs/`（保護用ファイルのみ）
- root `.htaccess`
- `robots.txt`
- Search Console verification file

Runtime生成データ、ログ本体、production-only helper、テスト用ファイル等はImport対象外。

## Import検証

- sanitized sourceのPHPは `php -l` で構文エラーなしを確認
- `public/contact.php`
- `public/event.php`
- `public/index.php`
- `public/ranking.php`
- `public/schedule.php`
  はZIP元ファイルとの一致を確認
- `inc/view_helpers.php` は内容同一で末尾改行差のみ確認
- `assets/css/site.css` はsource内容と本番表示を確認

## 本番Live Verification

Opera接続を使い、2026-09-06に本番で以下を確認済み。

### HOME
- HOME / RANKING / SCHEDULE 上部ナビ
- NEXT EVENT
- RANKING TOP5
- UPCOMING SCHEDULE
- LATEST VIDEO
- footer / contact導線
- schedule / ranking updated表示

### RANKING
- S.ONE / S.TWO / MASTERS切替
- SHORT / LONG / MEN / WOMEN切替
- 各戦ポイント
- 未開催 `—`
- 開催済み0点 `0`
- 合計
- `現在：第1戦終了 / 全4戦`
- Instagramリンク / contact案内

### SCHEDULE
- event card
- status / 日付 / 会場 / league / board
- TBDセクション
- footer注意書き
- `S.LEAGUE公式サイト` リンク
- contact導線

### CONTACT
- お問い合わせページ表示
- 入力項目と必須/任意表示
- 送信ボタン
- footer

フォーム送信そのものは今回のLive Verificationでは実行していない。

## ヘッダーナビ確定仕様

`HOME | RANKING | SCHEDULE`

- ページ最上部
- 隙間なし3分割
- 高さ約60〜64px
- 全面クリック可能
- 現在ページはシアン
- PC/スマホ共通
- footer固定ナビは削除済み

## schedule.php 安定版ルール

- 現在のevent cardを正常基準とする
- scheduleカード構造を不用意に変更しない
- event-card系CSSを不用意に変更しない
- narrow fixで無関係なCSSを触らない

注意書き:
- 上部の大きな注意喚起ボックスなし
- 上部の公式サイトボタンなし
- 下部既存注意書きを維持
- 文中の `S.LEAGUE公式サイト` のみ公式サイトへリンク

## RANKING 安定版ルール

壊さない対象:
- ranking table構造
- 同順位の入力順
- 各戦ポイント
- 未開催 `—`
- 開催済み0点 `0`
- 合計
- category切替
- season summary / progress

## SEO / 公開状態

実装済み:
- title
- meta description
- canonical
- OGP
- Twitter Card
- EVENT動的SEO
- 条件付きEVENT構造化データ

公開状態:
- public indexing有効
- Google Search Console所有権確認済み
- verification fileは維持する

## 本番反映

現状はFileZillaによる手動アップロード。

- GitHub mergeとproduction deployは別
- 本番変更Taskでは、確認後に変更対象を絞って反映する
- 本番操作はProject `AGENTS.md` の境界に従う

## 絶対に壊さない

- scheduleカードの正常レイアウト
- event-card系CSS
- ranking table / 各戦ポイント / 合計
- future `—` / held zero `0`
- fetch / parser / cron
- header nav確定デザイン
- EVENT routing / grouping
- CONTACT既存動作
- root `.htaccess` routing whitelistとの整合

## 次の再開点

初回ソースImportは完了済み。次回は「初回push」から始めない。

新しいTask開始時:
1. `oosaka0123-sudo/ai-master` の必要な開始ファイルを確認
2. このRepositoryのcurrent `main`
3. `README.md`
4. `AGENTS.md`
5. `HANDOFF.md`
6. Open Issues / Open PRs / Latest Actions / current code
7. 新しいTaskを小さなbranch / PRで進める

GitHub実態とこの文書が違う場合は、GitHub実態を正としてこのHANDOFFを更新する。
