<?php

use common\services\ContentUrlSigner;

require __DIR__ . '/../../vendor/autoload.php';

const HOST_PAGE = '.scorm-host.html';

const MIME_TYPES = [
    'html' => 'text/html; charset=utf-8',
    'htm' => 'text/html; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8',
    'mjs' => 'text/javascript; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'xsd' => 'application/xml',
    'txt' => 'text/plain; charset=utf-8',
    'vtt' => 'text/vtt',
    'srt' => 'text/plain; charset=utf-8',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'bmp' => 'image/bmp',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogv' => 'video/ogg',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'otf' => 'font/otf',
    'eot' => 'application/vnd.ms-fontobject',
    'pdf' => 'application/pdf',
    'swf' => 'application/x-shockwave-flash',
];

$params = require __DIR__ . '/../../common/config/params.php';
$debug = filter_var(getenv('YII_DEBUG'), FILTER_VALIDATE_BOOL);

sendSecurityHeaders($params['scormPlayerUrl'], $params['mainAppUrl']);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($path === '/scorm-api.js') {
    sendFile(__DIR__ . '/scorm-api.js', MIME_TYPES['js']);
    return true;
}

if (!preg_match('#^/c/(\d+)/(\d+)/([a-f0-9]{32})/(.+)$#', $path, $matches)) {
    notFound();
    return true;
}

[, $packageId, $expiresAt, $signature, $relativePath] = $matches;

if (!ContentUrlSigner::fromParams($params, $debug)->verify((int)$packageId, (int)$expiresAt, $signature)) {
    http_response_code(403);
    return true;
}

if ($relativePath === HOST_PAGE) {
    renderHostPage(is_string($_GET['launch'] ?? null) ? $_GET['launch'] : '', $params['scormPlayerUrl']);
    return true;
}

$base = realpath($params['scormContentPath'] . '/' . $packageId);
$file = $base ? realpath($base . '/' . $relativePath) : false;
$extension = strtolower(pathinfo((string)$file, PATHINFO_EXTENSION));

if (!$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file) || !isset(MIME_TYPES[$extension])) {
    notFound();
    return true;
}

sendFile($file, MIME_TYPES[$extension]);
return true;

function sendSecurityHeaders(string $playerOrigin, string $mainAppOrigin): void
{
    header(
        "Content-Security-Policy: default-src 'self' data: blob:; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob:; media-src 'self' data: blob:; font-src 'self' data:; "
        . "connect-src 'self'; frame-src 'self'; object-src 'self'; "
        . "form-action 'self'; base-uri 'self'; "
        . "frame-ancestors 'self' {$playerOrigin} {$mainAppOrigin}"
    );
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cross-Origin-Resource-Policy: same-origin');
}

function sendFile(string $file, string $mimeType): void
{
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=3600');
    readfile($file);
}

function notFound(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
}

function renderHostPage(string $launch, string $playerOrigin): void
{
    if ($launch === '' || preg_match('#^([a-z][a-z0-9+.-]*:|[/\\\\])#i', $launch) || str_contains($launch, '..')) {
        http_response_code(400);
        return;
    }

    $attribute = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>SCORM content</title>'
        . '<style>html,body,iframe{margin:0;width:100%;height:100%;border:0;overflow:hidden;display:block}</style></head>'
        . '<body data-launch="' . $attribute($launch) . '" data-player-origin="' . $attribute($playerOrigin) . '">'
        . '<iframe id="sco" title="SCORM content" allow="fullscreen; autoplay"></iframe>'
        . '<script src="/scorm-api.js"></script></body></html>';
}
