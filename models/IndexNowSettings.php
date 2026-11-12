<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * The service's settings, kept in app/config/services/osmium-indexnow.json.php: whether pings are on, and the key.
 * The key is public by design (IndexNow checks it at /<key>.txt), so the file holds nothing secret.
 */
class IndexNowSettings
{
    public const PATH = 'app/config/services/osmium-indexnow.json.php';

    /** @return array{enabled: bool, key: string} */
    public static function get(string $path = self::PATH): array
    {
        $defaults = ['enabled' => false, 'key' => ''];

        $fileExists = \file_exists($path);
        if (!$fileExists) return $defaults;

        $content = (string) \file_get_contents($path);
        $jsonStart = \strpos(haystack: $content, needle: '{');
        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) return $defaults;

        $decoded = \json_decode(\substr(string: $content, offset: $jsonStart), associative: true);
        $saved = \is_array($decoded) ? ($decoded['indexnow'] ?? []) : [];
        $key = \is_array($saved) ? (string) ($saved['key'] ?? '') : '';

        return [
            'enabled' => \is_array($saved) && !empty($saved['enabled']) && self::isValidKey($key),
            'key' => self::isValidKey($key) ? $key : '',
        ];
    }

    public static function save(bool $enabled, string $key, string $path = self::PATH): void
    {
        $dir = \dirname($path);
        $dirMissing = !\is_dir($dir);
        if ($dirMissing) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $json = \json_encode(
            value: ['indexnow' => ['enabled' => $enabled, 'key' => $key]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
        \file_put_contents($path, "<?php exit(); ?>\n" . $json . "\n");
    }

    /** IndexNow keys are 8 to 128 characters of letters, digits and dashes; we make 32 hex characters */
    public static function isValidKey(string $key): bool
    {
        return \preg_match(pattern: '/^[a-zA-Z0-9-]{8,128}$/', subject: $key) === 1;
    }

    public static function newKey(): string
    {
        return \bin2hex(\random_bytes(16));
    }
}
