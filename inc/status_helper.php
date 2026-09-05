<?php
/**
 * status_helper.php
 * ステータス判定は「公式ラベルを正とし、取得できない場合のみ日付で補完」。
 * 延期/中止/調整中などをAIやロジックで推測することは絶対にしない(指示書13, 29番)。
 */

const STATUS_FINISHED = 'FINISHED';
const STATUS_NOW = 'NOW';
const STATUS_UPCOMING = 'UPCOMING';
const STATUS_UNKNOWN = 'UNKNOWN'; // 公式ラベルも日付も無く判定不能な場合(推測はしない)

function resolve_status(array $event, ?DateTimeImmutable $today = null): string
{
    $today = $today ?? new DateTimeImmutable('today');

    // 1. 公式ラベルがあれば最優先で採用
    $map = [
        '終了' => STATUS_FINISHED,
        '開催中' => STATUS_NOW,
        '開催予定' => STATUS_UPCOMING,
    ];
    if (isset($event['status_raw']) && isset($map[$event['status_raw']])) {
        return $map[$event['status_raw']];
    }

    // 2. 公式ラベルが取れない場合のみ日付でフォールバック
    if ($event['date_confirmed'] ?? false) {
        $start = new DateTimeImmutable($event['start_date']);
        $end = new DateTimeImmutable($event['end_date']);
        if ($today < $start) return STATUS_UPCOMING;
        if ($today > $end) return STATUS_FINISHED;
        return STATUS_NOW;
    }

    // 3. どちらも無い(調整中など) → 推測しない
    return STATUS_UNKNOWN;
}

/**
 * DAY表示の自動計算(開催中のみ意味を持つ)
 */
function resolve_day_number(array $event, ?DateTimeImmutable $today = null): ?int
{
    if (!($event['date_confirmed'] ?? false)) {
        return null;
    }
    $today = $today ?? new DateTimeImmutable('today');
    $start = new DateTimeImmutable($event['start_date']);
    $diff = $today->diff($start)->days;
    return $today >= $start ? $diff + 1 : null;
}

/**
 * CURRENT EVENT: ステータスがNOWのものを返す(複数ある場合は先頭)。無ければnull。
 */
function find_current_event(array $events, ?DateTimeImmutable $today = null): ?array
{
    foreach ($events as $e) {
        if (resolve_status($e, $today) === STATUS_NOW) {
            $e['day_number'] = resolve_day_number($e, $today);
            return $e;
        }
    }
    return null;
}

/**
 * NEXT EVENT: 日付確定済みで、開始日が今日以降の中で最も近いもの。
 * 「調整中」で日付未確定のものはNEXT EVENT候補から除外する(指示書6番)。
 */
function find_next_event(array $events, ?DateTimeImmutable $today = null): ?array
{
    $today = $today ?? new DateTimeImmutable('today');
    $candidates = array_filter($events, function ($e) use ($today) {
        if (!($e['date_confirmed'] ?? false)) {
            return false;
        }
        $start = new DateTimeImmutable($e['start_date']);
        return $start >= $today;
    });

    if (empty($candidates)) {
        return null;
    }

    usort($candidates, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));
    $next = $candidates[array_key_first($candidates)];
    $start = new DateTimeImmutable($next['start_date']);
    $next['days_until'] = $today->diff($start)->days;
    return $next;
}

/**
 * UPCOMING SCHEDULE(HOME用): CURRENTの次以降、日付確定済みの大会を開催日順に最大$limit件返す。
 * find_next_event()と同じ「確定日・今日以降」の判定ロジックを再利用しているため、
 * 先頭の1件は基本的にNEXT EVENTと同じ大会になる(HOME側で判定ロジックを新しく作らないため)。
 * 調整中(date_confirmed=false)の大会はここには含めない(指示書8番)。
 */
function find_upcoming_events(array $events, int $limit = 5, ?DateTimeImmutable $today = null): array
{
    $today = $today ?? new DateTimeImmutable('today');
    $candidates = array_filter($events, function ($e) use ($today) {
        if (!($e['date_confirmed'] ?? false)) {
            return false;
        }
        return new DateTimeImmutable($e['start_date']) >= $today;
    });

    usort($candidates, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));

    return array_slice(array_values($candidates), 0, $limit);
}
