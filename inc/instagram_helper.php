<?php
declare(strict_types=1);

/**
 * inc/instagram_helper.php
 *
 * data/instagram_targets.json から verified=true のInstagram情報だけを読み込む。
 * ranking.php などから安全に参照するための共通ヘルパー。
 */

function sleague_load_verified_instagram_map(): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $path = dirname(__DIR__) . '/data/instagram_targets.json';

    if (!is_file($path)) {
        return $cache = [];
    }

    $raw = @file_get_contents($path);

    if (!is_string($raw) || $raw === '') {
        return $cache = [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return $cache = [];
    }

    $map = [];

    foreach ($data['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = trim((string)($item['name'] ?? ''));
        $verified = (bool)($item['verified'] ?? false);
        $instagram = trim((string)($item['instagram'] ?? ''));
        $username = trim((string)($item['username'] ?? ''));

        if ($name === '' || !$verified || $instagram === '' || $username === '') {
            continue;
        }

        $map[$name] = [
            'username' => $username,
            'instagram' => $instagram,
            'verified' => true,
            'verified_at' => $item['verified_at'] ?? null,
            'verification_type' => $item['verification_type'] ?? null,
        ];
    }

    return $cache = $map;
}

function sleague_get_verified_instagram(string $name): ?array
{
    $name = trim($name);

    if ($name === '') {
        return null;
    }

    $map = sleague_load_verified_instagram_map();

    return $map[$name] ?? null;
}
