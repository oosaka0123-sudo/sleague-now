<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dataPath = $root . '/data/instagram_targets.json';
$batchSize = 5;
$timeoutSeconds = 20;

$apiKey = trim((string)getenv('BRAVE_API_KEY'));
if ($apiKey === '') {
    fwrite(STDERR, "BRAVE_API_KEY is not available in this terminal.\n");
    fwrite(STDERR, "Open a NEW Command Prompt and run again.\n");
    exit(1);
}

if (!is_file($dataPath)) {
    fwrite(STDERR, "instagram_targets.json not found: {$dataPath}\n");
    exit(1);
}

$raw = file_get_contents($dataPath);
$data = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    fwrite(STDERR, "Invalid instagram_targets.json\n");
    exit(1);
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP curl extension is required.\n");
    exit(1);
}

function braveSearch(string $query, string $apiKey, int $timeout): array {
    $url = 'https://api.search.brave.com/res/v1/web/search?' . http_build_query([
        'q' => $query,
        'country' => 'JP',
        'search_lang' => 'ja',
        'ui_lang' => 'ja-JP',
        'count' => 20,
        'safesearch' => 'moderate',
    ], '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Encoding: gzip',
            'X-Subscription-Token: ' . $apiKey,
        ],
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $decoded = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($decoded)) {
        return ['ok'=>false,'status'=>$status,'data'=>$decoded,'error'=>$error !== '' ? $error : 'HTTP '.$status];
    }
    return ['ok'=>true,'status'=>200,'data'=>$decoded,'error'=>''];
}

function normalizeInstagram(string $url): ?array {
    if (!preg_match('~^https?://(?:www\.)?instagram\.com/([^/?#]+)/?~i', trim($url), $m)) return null;
    $username = trim($m[1]);
    if (!preg_match('/^[A-Za-z0-9._]{1,30}$/', $username)) return null;
    $blocked = ['p','reel','reels','stories','explore','accounts','direct','developer','about','legal','web','tv'];
    if (in_array(mb_strtolower($username), $blocked, true)) return null;
    return ['url'=>'https://www.instagram.com/'.$username.'/','username'=>$username];
}

function candidates(array $data): array {
    $out = [];
    $results = $data['web']['results'] ?? [];
    if (!is_array($results)) return [];
    foreach ($results as $rank => $r) {
        if (!is_array($r)) continue;
        $p = normalizeInstagram((string)($r['url'] ?? ''));
        if (!$p) continue;
        $p['title'] = (string)($r['title'] ?? '');
        $p['description'] = (string)($r['description'] ?? '');
        $p['rank'] = (int)$rank;
        $out[mb_strtolower($p['username'])] = $p;
    }
    return array_values($out);
}

function score(string $name, array $c): int {
    $s = 35;
    $text = mb_strtolower(($c['title'] ?? '').' '.($c['description'] ?? ''));
    $nl = mb_strtolower($name);
    if ($nl !== '' && mb_strpos($text, $nl) !== false) $s += 35;
    if (mb_strpos($text,'surf') !== false || mb_strpos($text,'サーフ') !== false || mb_strpos($text,'jpsa') !== false || mb_strpos($text,'s.league') !== false) $s += 15;
    if (($c['rank'] ?? 99) <= 2) $s += 5;
    return min(95, $s);
}

function saveData(string $path, array $data): bool {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tmp = $path.'.tmp';
    if (file_put_contents($tmp, $json.PHP_EOL, LOCK_EX) === false) return false;
    if (!rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

$processed=$found=$notFound=$errors=0;

foreach ($data['items'] as $i => $item) {
    if ($processed >= $batchSize) break;
    if (!is_array($item)) continue;

    $status = (string)($item['status'] ?? 'pending');
    if (!in_array($status, ['pending','not_found','error'], true)) continue;

    $name = trim((string)($item['name'] ?? ''));
    if ($name === '') continue;

    $processed++;
    echo "[{$processed}/{$batchSize}] search: {$name}\n";

    $queries = [
        '"'.$name.'" Instagram サーフィン',
        '"'.$name.'" Instagram JPSA',
        '"'.$name.'" site:instagram.com'
    ];

    $all = [];
    $successful = false;
    $lastError = '';

    foreach ($queries as $n => $q) {
        $r = braveSearch($q, $apiKey, $timeoutSeconds);
        if (!$r['ok']) {
            $lastError = 'HTTP '.$r['status'].': '.$r['error'];
            echo "  query ".($n+1)." error: {$lastError}\n";
            if (in_array($r['status'], [401,402,403,429], true)) break;
            continue;
        }
        $successful = true;
        foreach (candidates($r['data']) as $c) {
            $all[mb_strtolower($c['username'])] = $c;
        }
        if ($all) break;
        usleep(300000);
    }

    $now = date(DATE_ATOM);

    if (!$successful && $lastError !== '') {
        $data['items'][$i]['status'] = 'error';
        $data['items'][$i]['searched_at'] = $now;
        $data['items'][$i]['search_error'] = $lastError;
        $errors++;
        echo "  ERROR: {$lastError}\n";
    } elseif (!$all) {
        $data['items'][$i]['instagram'] = null;
        $data['items'][$i]['username'] = null;
        $data['items'][$i]['confidence'] = 0;
        $data['items'][$i]['verified'] = false;
        $data['items'][$i]['status'] = 'not_found';
        $data['items'][$i]['searched_at'] = $now;
        unset($data['items'][$i]['search_error']);
        $notFound++;
        echo "  not found\n";
    } else {
        $best = null; $bestScore = -1;
        foreach ($all as $c) {
            $sc = score($name, $c);
            if ($sc > $bestScore) { $best=$c; $bestScore=$sc; }
        }
        if ($best) {
            $data['items'][$i]['instagram'] = $best['url'];
            $data['items'][$i]['username'] = $best['username'];
            $data['items'][$i]['confidence'] = $bestScore;
            $data['items'][$i]['verified'] = false;
            $data['items'][$i]['status'] = 'candidate';
            $data['items'][$i]['searched_at'] = $now;
            $data['items'][$i]['candidate_count'] = count($all);
            $data['items'][$i]['search_source'] = 'brave';
            unset($data['items'][$i]['search_error']);
            $found++;
            echo "  candidate: @{$best['username']} confidence={$bestScore}\n";
        }
    }

    $data['updated_at'] = date(DATE_ATOM);
    if (!saveData($dataPath, $data)) {
        fwrite(STDERR, "Failed to save instagram_targets.json\n");
        exit(1);
    }
    usleep(300000);
}

echo "instagram search: OK processed={$processed} candidate={$found} not_found={$notFound} error={$errors}\n";
echo "saved: {$dataPath}\n";
