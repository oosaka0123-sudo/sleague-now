<?php
/**
 * inc/contact_config.php
 * お問い合わせフォームの送信設定。
 *
 * 本番アップロード前に CONTACT_TO_EMAIL と CONTACT_FROM_EMAIL を必ず変更すること。
 * このファイルは public/ 配下に置かないこと（直接アクセス不可の inc/ に置く）。
 */

// ----------------------------------------------------------------
// 送信先メールアドレス
define('CONTACT_TO_EMAIL',   'sleague@rss7.net');

// 送信元（From）メールアドレス
// 送信先と同じアドレスにすることで、ロリポップ側のSPF制約を回避しやすくなる。
define('CONTACT_FROM_EMAIL', 'sleague@rss7.net');
// ----------------------------------------------------------------

// 連投制限: 同一IPから10分間に許容する送信回数
define('CONTACT_RATE_LIMIT_MAX',    3);

// 連投制限の時間窓（秒）
define('CONTACT_RATE_LIMIT_WINDOW', 600);

// フォーム表示からこの秒数未満の送信はbot扱いで拒否
define('CONTACT_MIN_TIME_SEC',      3);
