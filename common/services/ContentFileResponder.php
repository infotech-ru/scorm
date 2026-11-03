<?php

namespace common\services;

use common\components\SelectelStorageS3;
use common\helpers\Helper;
use DomainException;
use Throwable;

final class ContentFileResponder
{
    public const array MIME_TYPES = [
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

    public function __construct(private readonly SelectelStorageS3 $storage)
    {
    }

    public function respond(string $prefix, string $relativePath, ?string $rangeHeader): array
    {
        $key = Helper::objectKeyPrefix($prefix, $relativePath);
        $mime = self::MIME_TYPES[strtolower(pathinfo($relativePath, PATHINFO_EXTENSION))] ?? null;

        if (!$key || !$mime ) {
            return $this->error(404);
        }

        $range = $rangeHeader && preg_match('/^bytes=(\d+-\d*|-\d+)\z/', $rangeHeader) ? $rangeHeader : null;

        try {
            $object = $this->storage->getObject($key, $range);
        } catch (DomainException $e) {
            return $this->error($e->getCode() === 416 ? 416 : 502, $e->getMessage());
        } catch (Throwable $e) {
            return $this->error(502, $e::class);
        }

        if (!$object) {
            return $this->error(404);
        }

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => $object['ContentLength'],
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
        ];
        $status = 200;

        if ($range && !empty($object['ContentRange'])) {
            $status = 206;
            $headers['Content-Range'] = $object['ContentRange'];
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $object['Body'], 'error' => null];
    }

    private function error(int $status, ?string $error = null): array
    {
        return ['status' => $status, 'headers' => ['Accept-Ranges' => 'bytes'], 'body' => null, 'error' => $error];
    }
}
