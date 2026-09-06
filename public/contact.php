<?php
/**
 * public/contact.php
 * お問い合わせフォーム（Instagramリンク報告 / 一般お問い合わせ共用）
 *
 * セキュリティ実装:
 *   - CSRF トークン（セッション）
 *   - Honeypot（position:absolute で画面外配置）
 *   - 最短送信時間チェック（3秒未満を拒否）
 *   - 連投制限（同一IPで10分間に3回まで、data/contact_submissions.json で管理）
 *   - ヘッダーインジェクション対策（ユーザー入力をメールヘッダーに使用しない）
 *   - 入力バリデーション＋sanitize
 *   - PRG（Post/Redirect/Get）パターンで二重送信防止
 */

// ================================================================
// 依存ファイル・送信設定
// ================================================================
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/contact_config.php'; // 送信先・送信元メールアドレスはこちらで設定
require_once __DIR__ . '/../inc/view_helpers.php';
require_once __DIR__ . '/../inc/seo_helper.php';

// ================================================================
// セッションセキュリティ設定（session_start() より前に行う）
// ================================================================
// cookie_httponly: JavaScriptからのセッションCookie読み取りを禁止
ini_set('session.cookie_httponly', '1');
// cookie_samesite: CSRF緩和のためSameSite=Laxを設定
ini_set('session.cookie_samesite', 'Lax');
// cookie_secure: HTTPS環境でのみCookieをHTTPS接続限定にする
//   ローカル開発環境(HTTP)では自動的に無効にするため、HTTPS検出で切り替える
$_contact_is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
if ($_contact_is_https) {
    ini_set('session.cookie_secure', '1');
}
unset($_contact_is_https);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

apply_robots_header();

// ================================================================
// CSRF
// ================================================================
function contact_csrf_token(): string
{
    if (empty($_SESSION['contact_csrf'])) {
        $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['contact_csrf'];
}

function contact_csrf_verify(string $token): bool
{
    return !empty($_SESSION['contact_csrf'])
        && hash_equals($_SESSION['contact_csrf'], $token);
}

function contact_csrf_refresh(): void
{
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

// ================================================================
// 連投制限（IPごとの送信履歴をJSONファイルで管理）
// ================================================================
function contact_rl_file(): string
{
    // DATA_DIR はウェブルート外 (data/)。Apache/Nginxでは直接閲覧不可。
    return DATA_DIR . '/contact_submissions.json';
}

function contact_is_rate_limited(string $ip): bool
{
    $file = contact_rl_file();
    if (!is_file($file)) {
        return false;
    }
    $data = json_decode(@file_get_contents($file), true);
    if (!is_array($data)) {
        return false;
    }
    $now    = time();
    $window = CONTACT_RATE_LIMIT_WINDOW;
    $recent = array_filter($data[$ip] ?? [], fn($t) => ($now - $t) < $window);
    return count($recent) >= CONTACT_RATE_LIMIT_MAX;
}

function contact_record_submission(string $ip): void
{
    $file = contact_rl_file();
    $fp   = @fopen($file, 'c+');
    if (!$fp) {
        return;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }
    $data = json_decode(stream_get_contents($fp), true);
    if (!is_array($data)) {
        $data = [];
    }
    $now    = time();
    $window = CONTACT_RATE_LIMIT_WINDOW;
    // 全IPの期限切れエントリを除去して肥大化を防ぐ
    foreach ($data as $storedIp => $times) {
        $data[$storedIp] = array_values(
            array_filter($times, fn($t) => ($now - $t) < $window)
        );
        if (empty($data[$storedIp])) {
            unset($data[$storedIp]);
        }
    }
    $data[$ip][] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
}

// ================================================================
// 入力整形・バリデーション
// ================================================================

/** HTMLタグ除去・前後空白除去・文字数上限 */
function contact_sanitize(string $v, int $max): string
{
    return mb_substr(trim(strip_tags($v)), 0, $max, 'UTF-8');
}

/** メールヘッダーインジェクション防止（改行・タブを除去） */
function contact_safe_header(string $v): string
{
    return preg_replace('/[\r\n\t]/', '', $v);
}

/**
 * @param array $post $_POST
 * @return array エラーメッセージの連想配列（空なら検証OK）
 */
function contact_validate(array $post): array
{
    $errors = [];

    $allowed = ['broken', 'wrong_account', 'other_instagram', 'other'];
    if (!in_array($post['issue_type'] ?? '', $allowed, true)) {
        $errors['issue_type'] = 'お問い合わせ内容を選択してください。';
    }

    $comment = contact_sanitize($post['comment'] ?? '', 1000);
    if ($comment === '') {
        $errors['comment'] = 'お問い合わせ内容・詳細を入力してください。';
    }

    $url = contact_sanitize($post['correct_url'] ?? '', 300);
    if ($url !== '') {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (filter_var($url, FILTER_VALIDATE_URL) === false || $scheme !== 'https') {
            $errors['correct_url'] = 'https:// から始まる正しいURLを入力してください。';
        }
    }

    // メールアドレス（任意・入力された場合のみ形式チェック）
    $email = contact_sanitize($post['reply_email'] ?? '', 254);
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['reply_email'] = 'メールアドレスの形式が正しくありません。';
    }

    return $errors;
}

// ================================================================
// メール送信（ユーザー入力をヘッダーに含めない）
// ================================================================
function contact_send_mail(array $data): bool
{
    $issueLabels = [
        'broken'          => 'Instagramリンク切れ',
        'wrong_account'   => '別人のInstagram',
        'other_instagram' => 'その他のInstagram修正',
        'other'           => 'その他のお問い合わせ',
    ];
    $issueLabel = $issueLabels[$data['issue_type']] ?? '不明';

    // 本文（プレーンテキスト・ユーザー入力はstrip_tags済み）
    $lines = [
        'S.LEAGUE NOW お問い合わせ',
        str_repeat('-', 40),
        '【お問い合わせ種別】 ' . $issueLabel,
        '',
    ];
    if ($data['reply_email'] !== '') {
        $lines[] = '【返信先メールアドレス】';
        $lines[] = $data['reply_email'];  // 本文のみ記載。Fromヘッダーには使用しない。
        $lines[] = '';
    }
    if ($data['player_name'] !== '') {
        $lines[] = '【選手名】';
        $lines[] = $data['player_name'];
        $lines[] = '';
    }
    if ($data['correct_url'] !== '') {
        $lines[] = '【正しいInstagram URL】';
        $lines[] = $data['correct_url'];
        $lines[] = '';
    }
    $lines[] = '【詳細・お問い合わせ内容】';
    $lines[] = $data['comment'];
    $lines[] = '';
    $lines[] = str_repeat('-', 40);
    $lines[] = '送信日時: ' . date('Y-m-d H:i:s');

    $body = implode("\r\n", $lines);

    // 件名: 固定文字列（ユーザー入力なし）
    $subject = mb_encode_mimeheader('[S.LEAGUE NOW] お問い合わせ', 'UTF-8', 'B');

    // ヘッダー: ユーザー入力は一切含めない
    $from    = contact_safe_header('S.LEAGUE NOW <' . CONTACT_FROM_EMAIL . '>');
    $headers = implode("\r\n", [
        'From: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);

    // @ でmail()自体の警告を抑制し、内部情報がHTMLに漏れないようにする
    return @mail(CONTACT_TO_EMAIL, $subject, $body, $headers);
}

// ================================================================
// リクエスト処理（PRGパターン）
// ================================================================
$showSuccess = false;
$formErrors  = [];
$formValues  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clientIp  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $csrfOk    = contact_csrf_verify($_POST['_csrf'] ?? '');
    $hpFilled  = (($_POST['hp_website'] ?? '') !== '');
    $shownAt   = (int) ($_SESSION['contact_form_shown_at'] ?? 0);
    $tooFast   = ($shownAt > 0 && (time() - $shownAt) < CONTACT_MIN_TIME_SEC);
    $rateLimited = contact_is_rate_limited($clientIp);

    // ---- セキュリティ拒否（詳細は伏せる） ----
    if (!$csrfOk || $hpFilled || $tooFast || $rateLimited) {
        contact_csrf_refresh();
        $_SESSION['contact_errors'] = ['_security' => '送信に失敗しました。しばらくしてから再度お試しください。'];
        $_SESSION['contact_input']  = [];
        header('Location: contact.php');
        exit;
    }

    // ---- バリデーション ----
    $errors = contact_validate($_POST);
    if (!empty($errors)) {
        contact_csrf_refresh();
        $_SESSION['contact_errors'] = $errors;
        $_SESSION['contact_input']  = [
            'player_name' => contact_sanitize($_POST['player_name'] ?? '', 100),
            'reply_email' => contact_sanitize($_POST['reply_email'] ?? '', 254),
            'issue_type'  => $_POST['issue_type'] ?? '',
            'correct_url' => contact_sanitize($_POST['correct_url'] ?? '', 300),
            'comment'     => contact_sanitize($_POST['comment'] ?? '', 1000),
        ];
        header('Location: contact.php');
        exit;
    }

    // ---- 送信 ----
    $data = [
        'player_name' => contact_sanitize($_POST['player_name'] ?? '', 100),
        'reply_email' => contact_sanitize($_POST['reply_email'] ?? '', 254),
        'issue_type'  => $_POST['issue_type'],
        'correct_url' => contact_sanitize($_POST['correct_url'] ?? '', 300),
        'comment'     => contact_sanitize($_POST['comment'] ?? '', 1000),
    ];

    if (contact_send_mail($data)) {
        contact_record_submission($clientIp);
        contact_csrf_refresh();
        unset($_SESSION['contact_form_shown_at']);
        $_SESSION['contact_success'] = true;
        header('Location: contact.php?sent=1');
        exit;
    }

    // メール送信失敗
    contact_csrf_refresh();
    $_SESSION['contact_errors'] = ['_send' => 'メールの送信に失敗しました。しばらくしてから再度お試しください。'];
    $_SESSION['contact_input']  = $data;
    header('Location: contact.php');
    exit;
}

// GET: 成功フラグ確認（PRGのGフェーズ）
if (isset($_GET['sent']) && !empty($_SESSION['contact_success'])) {
    $showSuccess = true;
    unset($_SESSION['contact_success']);
}

// GET: セッションからエラー・入力値を取り出す
if (!$showSuccess) {
    $formErrors = $_SESSION['contact_errors'] ?? [];
    $formValues = $_SESSION['contact_input']  ?? [];
    unset($_SESSION['contact_errors'], $_SESSION['contact_input']);
    // フォーム表示時刻を記録（最短送信時間チェック用）
    $_SESSION['contact_form_shown_at'] = time();
}

$csrfToken = contact_csrf_token();

// ================================================================
// ヘルパー: テンプレート用
// ================================================================

/** 選択肢がセッション復元値と一致すれば checked を返す */
function is_selected_issue(string $value, array $formValues): string
{
    return ($formValues['issue_type'] ?? '') === $value ? ' checked' : '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php render_seo_head(
    'お問い合わせ｜' . SITE_NAME,
    'Instagramリンク切れ・誤リンクのご報告、その他S.LEAGUE NOWへのお問い合わせはこちらからお願いします。',
    '/contact.php'
); ?>
<link rel="stylesheet" href="../assets/css/site.css">
</head>
<body>

<?php if (IS_DEMO_MODE): ?>
<div class="demo-banner"><?= h(DEMO_BANNER_TEXT) ?></div>
<?php endif; ?>

<header class="site-header">
  <?php render_header_nav(''); ?>
  <div class="site-header__eyebrow">S.LEAGUE 2026-27 SEASON</div>
  <h1 class="site-header__title">CONTACT</h1>
</header>

<main class="container">

  <h2 class="ranking-category-title">お問い合わせ</h2>

  <?php if ($showSuccess): ?>

    <div class="contact-thanks">
      <p class="contact-thanks__title">お問い合わせありがとうございます</p>
      <p class="contact-thanks__body">
        いただいた内容をもとに確認いたします。
      </p>
    </div>
    <a class="contact-back" href="ranking.php">← ランキングに戻る</a>

  <?php else: ?>

    <p class="contact-intro">
      Instagramリンク切れ・誤リンクのご報告、その他S.LEAGUE NOWへのお問い合わせはこちらからお願いします。
    </p>

    <?php if (!empty($formErrors['_security']) || !empty($formErrors['_send'])): ?>
      <div class="contact-alert contact-alert--error">
        <?= h($formErrors['_security'] ?? $formErrors['_send'] ?? '') ?>
      </div>
    <?php endif; ?>

    <form class="contact-form" method="post" action="contact.php">

      <!-- CSRF トークン -->
      <input type="hidden" name="_csrf" value="<?= h($csrfToken) ?>">

      <!-- Honeypot: 通常の利用者には見えない（CSSで画面外配置） -->
      <div class="contact-hp" aria-hidden="true">
        <label for="hp_website">ウェブサイト URL</label>
        <input type="text" id="hp_website" name="hp_website"
               tabindex="-1" autocomplete="off" value="">
      </div>

      <!-- 選手名（任意） -->
      <div class="contact-form__group">
        <label class="contact-form__label" for="player-name">
          選手名（Instagram関連の場合）
          <span class="contact-form__optional">任意</span>
        </label>
        <input
          class="contact-form__input<?= isset($formErrors['player_name']) ? ' contact-form__input--error' : '' ?>"
          type="text"
          id="player-name"
          name="player_name"
          placeholder="例：田中　大輝"
          maxlength="100"
          autocomplete="off"
          value="<?= h($formValues['player_name'] ?? '') ?>"
        >
        <?php if (!empty($formErrors['player_name'])): ?>
          <p class="contact-form__error-text"><?= h($formErrors['player_name']) ?></p>
        <?php endif; ?>
      </div>

      <!-- メールアドレス（任意） -->
      <div class="contact-form__group">
        <label class="contact-form__label" for="reply-email">
          メールアドレス
          <span class="contact-form__optional">任意</span>
        </label>
        <p class="contact-form__hint">返信をご希望の場合は入力してください</p>
        <input
          class="contact-form__input<?= isset($formErrors['reply_email']) ? ' contact-form__input--error' : '' ?>"
          type="email"
          id="reply-email"
          name="reply_email"
          placeholder="example@email.com"
          maxlength="254"
          autocomplete="email"
          value="<?= h($formValues['reply_email'] ?? '') ?>"
        >
        <?php if (!empty($formErrors['reply_email'])): ?>
          <p class="contact-form__error-text"><?= h($formErrors['reply_email']) ?></p>
        <?php endif; ?>
      </div>

      <!-- お問い合わせ内容（必須） -->
      <div class="contact-form__group">
        <fieldset class="contact-form__fieldset<?= isset($formErrors['issue_type']) ? ' contact-form__fieldset--error' : '' ?>">
          <legend>
            お問い合わせ内容
            <span class="contact-form__required">必須</span>
          </legend>
          <div class="contact-form__radio-group">
            <label class="contact-form__radio-label">
              <input type="radio" name="issue_type" value="broken" required<?= is_selected_issue('broken', $formValues) ?>>
              Instagramリンク切れ
            </label>
            <label class="contact-form__radio-label">
              <input type="radio" name="issue_type" value="wrong_account"<?= is_selected_issue('wrong_account', $formValues) ?>>
              別人のInstagram
            </label>
            <label class="contact-form__radio-label">
              <input type="radio" name="issue_type" value="other_instagram"<?= is_selected_issue('other_instagram', $formValues) ?>>
              その他のInstagram修正
            </label>
            <label class="contact-form__radio-label">
              <input type="radio" name="issue_type" value="other"<?= is_selected_issue('other', $formValues) ?>>
              その他のお問い合わせ
            </label>
          </div>
          <?php if (!empty($formErrors['issue_type'])): ?>
            <p class="contact-form__error-text"><?= h($formErrors['issue_type']) ?></p>
          <?php endif; ?>
        </fieldset>
      </div>

      <!-- 正しいInstagram URL（任意） -->
      <div class="contact-form__group">
        <label class="contact-form__label" for="correct-url">
          正しいInstagram URL（分かる場合のみ）
          <span class="contact-form__optional">任意</span>
        </label>
        <input
          class="contact-form__input<?= isset($formErrors['correct_url']) ? ' contact-form__input--error' : '' ?>"
          type="url"
          id="correct-url"
          name="correct_url"
          placeholder="https://www.instagram.com/..."
          maxlength="300"
          autocomplete="off"
          value="<?= h($formValues['correct_url'] ?? '') ?>"
        >
        <?php if (!empty($formErrors['correct_url'])): ?>
          <p class="contact-form__error-text"><?= h($formErrors['correct_url']) ?></p>
        <?php endif; ?>
      </div>

      <!-- お問い合わせ内容・詳細（必須） -->
      <div class="contact-form__group">
        <label class="contact-form__label" for="comment">
          お問い合わせ内容・詳細
          <span class="contact-form__required">必須</span>
        </label>
        <textarea
          class="contact-form__textarea<?= isset($formErrors['comment']) ? ' contact-form__textarea--error' : '' ?>"
          id="comment"
          name="comment"
          rows="4"
          required
          placeholder="お問い合わせ内容をご記入ください"
          maxlength="1000"
        ><?= h($formValues['comment'] ?? '') ?></textarea>
        <?php if (!empty($formErrors['comment'])): ?>
          <p class="contact-form__error-text"><?= h($formErrors['comment']) ?></p>
        <?php endif; ?>
      </div>

      <button class="contact-form__submit" type="submit">送信する</button>

    </form>

    <a class="contact-back" href="ranking.php">← ランキングに戻る</a>

  <?php endif; ?>

</main>

<footer class="site-footer container">
  <div class="site-footer__disclaimer">
    当サイトはS.LEAGUE/JPSAの公式サイトではありません。非公式・非営利のファンサイトです。
  </div>
  <a class="site-footer__external-link" href="https://sleague.jp/" target="_blank" rel="noopener">S.LEAGUE公式サイトを見る 〉〉</a>
  <a class="site-footer__contact-link" href="contact.php">お問い合わせ</a>
</footer>

</body>
</html>
