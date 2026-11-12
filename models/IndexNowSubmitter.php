<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * Checks the key file (settings is IndexNowSettings::get()), sends a batch of URLs and logs the answer. Used by the page hooks and by Submit all pages.
 * Never throws: a failed ping must not break the save that caused it.
 */
class IndexNowSubmitter
{
    public function __construct(
        private IndexNowSite $site,
        private IndexNowLog $log,
        private IndexNowClient $client,
        private string $documentRoot,
        private array $settings,
    ) {}

    /**
     * @param string[] $urls full URLs on this site
     * @return int the HTTP status IndexNow answered with, or 0 when nothing was sent
     */
    public function submit(array $urls, string $reason): int
    {
        $settings = $this->settings;
        $isOn = $settings['enabled'] && $this->site->isLive && $urls !== [];
        if (!$isOn) return 0;

        $urls = $this->withoutRecentRepeats($urls, $reason);
        if ($urls === []) return 0;

        $keyFileReady = IndexNowKeyFile::ensure(documentRoot: $this->documentRoot, key: $settings['key']);
        if (!$keyFileReady) return 0;

        $payload = IndexNowClient::payload(
            host: $this->site->host(),
            key: $settings['key'],
            keyLocation: $this->site->baseUrl . '/' . $settings['key'] . '.txt',
            urls: $urls,
        );

        try {
            $httpCode = $this->client->send($payload);
            $this->log->record(urls: $payload['urlList'], reason: $reason, httpCode: $httpCode);
            $this->log->prune();
        } catch (\Throwable $e) {
            \error_log('IndexNow: ' . $e->getMessage());
            return 0;
        }

        return $httpCode;
    }

    /**
     * Saving a page again within 10 minutes of an accepted ping for it sends nothing new. Send all pages always goes through.
     *
     * @param string[] $urls
     * @return string[]
     */
    private function withoutRecentRepeats(array $urls, string $reason): array
    {
        $isBatch = $reason === 'all pages';
        if ($isBatch) return $urls;

        return \array_values(\array_filter($urls, fn(string $url): bool => !$this->log->recentlySent($url, $reason)));
    }
}
