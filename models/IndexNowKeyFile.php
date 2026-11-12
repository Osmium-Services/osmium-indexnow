<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * The key file IndexNow fetches to prove the site owns the key: <web root>/<key>.txt, containing just the key.
 * A deploy that replaces the web root can drop it, so every submission checks it is still there first.
 */
class IndexNowKeyFile
{
    public static function path(string $documentRoot, string $key): string
    {
        return \rtrim($documentRoot, '/') . '/' . $key . '.txt';
    }

    public static function ensure(string $documentRoot, string $key): bool
    {
        $valid = IndexNowSettings::isValidKey($key);
        if (!$valid) return false;

        $path = self::path($documentRoot, $key);
        $isCurrent = \is_file($path) && \trim((string) \file_get_contents($path)) === $key;
        if ($isCurrent) return true;

        return \file_put_contents($path, $key) !== false;
    }

    public static function remove(string $documentRoot, string $key): void
    {
        $valid = IndexNowSettings::isValidKey($key);
        if (!$valid) return;

        $path = self::path($documentRoot, $key);
        $exists = \is_file($path);
        if ($exists) \unlink($path);
    }
}
