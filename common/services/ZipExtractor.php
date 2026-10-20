<?php

namespace common\services;

use RuntimeException;
use ZipArchive;

class ZipExtractor
{
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'htaccess',
    ];

    public function __construct(
        private readonly int $maxFiles = 10000,
        private readonly int $maxBytes = 500 * 1024 * 1024
    ) {
    }

    public function extractSafely(string $zipPath, string $destinationDir): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        if ($result !== true) {
            throw new RuntimeException("Cannot open ZIP archive (error {$result})");
        }

        try {
            $entries = $this->inspect($zip);

            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true)) {
                throw new RuntimeException('Cannot create destination directory');
            }
            $base = rtrim(str_replace('\\', '/', realpath($destinationDir)), '/');

            $written = 0;
            $extracted = [];
            foreach ($entries as $index => $name) {
                $target = $this->resolveTarget($base, $name);

                if (str_ends_with($name, '/')) {
                    if (!is_dir($target) && !mkdir($target, 0755, true)) {
                        throw new RuntimeException('Cannot create directory in package');
                    }
                    continue;
                }

                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
                    throw new RuntimeException('Cannot create directory in package');
                }

                $written += $this->copyEntry($zip, $index, $target, $this->maxBytes - $written);
                $extracted[] = $name;
            }

            return $extracted;
        } finally {
            $zip->close();
        }
    }

    public function hasManifest(string $zipPath): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $hasManifest = $zip->locateName('imsmanifest.xml') !== false;
        $zip->close();

        return $hasManifest;
    }

    private function inspect(ZipArchive $zip): array
    {
        if ($zip->numFiles > $this->maxFiles) {
            throw new RuntimeException("Package contains more than {$this->maxFiles} files");
        }

        $entries = [];
        $declaredSize = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', $stat['name']);

            if (str_starts_with($name, '__MACOSX/') || str_starts_with(basename($name), '.')) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
                throw new RuntimeException('Package contains a forbidden file type: .' . $extension);
            }

            $declaredSize += $stat['size'];
            if ($declaredSize > $this->maxBytes) {
                throw new RuntimeException('Package is too large when extracted');
            }

            $entries[$i] = $name;
        }

        return $entries;
    }

    private function resolveTarget(string $base, string $name): string
    {
        if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name)) {
            throw new RuntimeException('Invalid path in archive');
        }

        $parts = [];
        foreach (explode('/', $name) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new RuntimeException('Invalid path in archive');
            }
            $parts[] = $part;
        }

        if (!$parts) {
            throw new RuntimeException('Invalid path in archive');
        }

        return $base . '/' . implode('/', $parts) . (str_ends_with($name, '/') ? '/' : '');
    }

    private function copyEntry(ZipArchive $zip, int $index, string $target, int $budget): int
    {
        $source = $zip->getStream($zip->getNameIndex($index));
        if ($source === false) {
            throw new RuntimeException('Cannot read file from archive');
        }

        $destination = fopen($target, 'wb');
        if ($destination === false) {
            fclose($source);
            throw new RuntimeException('Cannot write extracted file');
        }

        $bytes = 0;
        try {
            while (!feof($source)) {
                $chunk = fread($source, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('Cannot read file from archive');
                }
                $bytes += strlen($chunk);
                if ($bytes > $budget) {
                    throw new RuntimeException('Package is too large when extracted');
                }
                fwrite($destination, $chunk);
            }
        } finally {
            fclose($source);
            fclose($destination);
        }

        return $bytes;
    }
}
