<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * Sends URLs to the IndexNow endpoint. One request reaches every participating search engine.
 */
class IndexNowClient
{
    public const ENDPOINT = 'https://api.indexnow.org/IndexNow';
    public const MAX_URLS = 10000;

    /**
     * @param string[] $urls
     * @return array<string, mixed>
     */
    public static function payload(string $host, string $key, string $keyLocation, array $urls): array
    {
        return [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => \array_slice(\array_values(\array_unique($urls)), 0, self::MAX_URLS),
        ];
    }

    /** What an IndexNow answer means, in words for the log */
    public static function describe(int $httpCode): string
    {
        return match ($httpCode) {
            200 => 'Accepted',
            202 => 'Accepted, key check pending',
            400 => 'Bad request',
            403 => 'Key not valid or key file not found',
            422 => 'URLs do not belong to this host',
            429 => 'Too many requests',
            0 => 'Could not reach IndexNow',
            default => 'Unexpected answer',
        };
    }

    /** @param array<string, mixed> $payload @return int the HTTP status, 0 when the request never got an answer */
    public function send(array $payload): int
    {
        $curl = \curl_init(self::ENDPOINT);
        \curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        \curl_exec($curl);
        $httpCode = (int) \curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        \curl_close($curl);

        return $httpCode;
    }
}
