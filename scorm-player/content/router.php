<?php

use common\components\SelectelStorageS3;
use common\services\ContentFileResponder;
use common\services\ContentUrlSigner;

require __DIR__ . '/../../vendor/autoload.php';

Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__, 2))->safeLoad();

require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';

const HOST_PAGE = '.scorm-host.html';

$params = require __DIR__ . '/../../common/config/params.php';
$debug = filter_var(getenv('YII_DEBUG'), FILTER_VALIDATE_BOOL);

sendSecurityHeaders("'none'");

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($path === '/scorm-api.js') {
    sendFile(__DIR__ . '/scorm-api.js', ContentFileResponder::MIME_TYPES['js']);
    return true;
}

if (!preg_match('#^/c/(\d+)/([A-Za-z0-9_-]+)/(\d+)/([A-Za-z0-9_-]+)/([a-f0-9]{32})/(.+)\z#', $path, $matches)) {
    notFound();
    return true;
}

[, $packageId, $encodedPrefix, $expiresAt, $encodedOrigin, $signature, $relativePath] = $matches;
$ancestorOrigin = ContentUrlSigner::decodeOrigin($encodedOrigin);
$packagePrefix = ContentUrlSigner::decodePrefix($encodedPrefix);

if (!$ancestorOrigin
    || !$packagePrefix
    || !ContentUrlSigner::fromParams($params, $debug)->verify($packageId, $packagePrefix, $expiresAt, $ancestorOrigin, $signature)
) {
    http_response_code(403);
    return true;
}

if ($relativePath === HOST_PAGE) {
    sendSecurityHeaders($params['scormPlayerUrl'] . ' ' . $ancestorOrigin);
    renderHostPage(is_string($_GET['launch'] ?? null) ? $_GET['launch'] : '', $params['scormPlayerUrl']);
    return true;
}

sendSecurityHeaders("'self' " . $params['scormPlayerUrl'] . ' ' . $ancestorOrigin);

try {
    /** @var SelectelStorageS3 $storage */
    $storage = Yii::createObject((require __DIR__ . '/../../common/config/main.php')['components']['storage']);
    $response = new ContentFileResponder($storage)->respond(
        $packagePrefix,
        $relativePath,
        is_string($_SERVER['HTTP_RANGE'] ?? null) ? $_SERVER['HTTP_RANGE'] : null
    );
} catch (Throwable $e) {
    error_log('Ошибка формирования ответа: ' . $packageId . ': ' . get_class($e));
    http_response_code(502);
    header('Accept-Ranges: bytes');
    return true;
}

foreach ($response['headers'] as $name => $value) {
    header($name . ': ' . $value);
}

if ($response['status'] === 404) {
    notFound();
    return true;
}

http_response_code($response['status']);

if ($response['body'] === null) {
    if ($response['status'] === 502) {
        error_log('Ошибка формирования ответа: ' . $packageId . ': ' . $response['error']);
    }
    return true;
}

try {
    while (!$response['body']->eof()) {
        echo $response['body']->read(8192);
        flush();
    }
} catch (Throwable $e) {
    error_log('Ошибка формирования ответа: ' . $packageId . ': ' . get_class($e));
}

return true;

function sendSecurityHeaders(string $frameAncestors): void
{
    header(
        "Content-Security-Policy: default-src 'self' data: blob:; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob:; media-src 'self' data: blob:; font-src 'self' data:; "
        . "connect-src 'self'; frame-src 'self'; object-src 'self'; "
        . "form-action 'self'; base-uri 'self'; "
        . "frame-ancestors $frameAncestors"
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
