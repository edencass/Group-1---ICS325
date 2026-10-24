<?php
declare(strict_types=1);

function normalize_url(?string $raw): ?string
{
    if ($raw === null) return null;
    $raw = trim(preg_replace('/\x{00A0}/u', ' ', $raw));
    if ($raw === '') return null;
    if (!preg_match('~^https?://~i', $raw)) $raw = 'https://' . $raw;

    $p = parse_url($raw);
    if ($p === false || empty($p['host'])) return null;

    $scheme = strtolower($p['scheme'] ?? 'https');
    if (!in_array($scheme, ['http', 'https'], true)) return null;

    $host = strtolower($p['host']);
    $port = $p['port'] ?? null;
    if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) $port = null;

    $path = $p['path'] ?? '/';
    if ($path !== '/') $path = rtrim($path, '/');

    return $scheme . '://' . $host . ($port ? ':' . $port : '') . $path;
}
