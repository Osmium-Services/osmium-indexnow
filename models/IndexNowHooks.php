<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * page.saved and page.removed: ping IndexNow for the page's URL (and, when an edit moved the page, its old one).
 * Pages marked noindex are not announced when saved; a removed page always is, so engines drop it.
 */
class IndexNowHooks
{
    /** @param array{url?: string, robots?: string, oldUrl?: ?string, dataSource?: object} $payload */
    public static function pageSaved(array $payload): void
    {
        $robots = (string) ($payload['robots'] ?? 'index,follow');
        $allowsIndexing = !\str_contains($robots, 'noindex');
        if (!$allowsIndexing) return;

        $slugs = [(string) ($payload['url'] ?? '')];
        $oldUrl = $payload['oldUrl'] ?? null;
        if ($oldUrl !== null) $slugs[] = (string) $oldUrl;

        self::announce(slugs: $slugs, reason: 'saved', payload: $payload);
    }

    /** @param array{url?: string, dataSource?: object} $payload */
    public static function pageRemoved(array $payload): void
    {
        self::announce(slugs: [(string) ($payload['url'] ?? '')], reason: 'removed', payload: $payload);
    }

    /** @param string[] $slugs @param array<string, mixed> $payload */
    private static function announce(array $slugs, string $reason, array $payload): void
    {
        $dataSource = $payload['dataSource'] ?? null;
        if ($dataSource === null) return;

        $settings = IndexNowSettings::get();
        if (!$settings['enabled']) return;

        $site = IndexNowSite::load();
        $urls = \array_map(fn(string $slug): string => $site->pageUrl($slug), $slugs);

        $submitter = new IndexNowSubmitter(
            site: $site,
            log: new IndexNowLog($dataSource),
            client: new IndexNowClient(),
            documentRoot: (string) $_SERVER['DOCUMENT_ROOT'],
            settings: $settings,
        );
        $submitter->submit(urls: $urls, reason: $reason);
    }
}
