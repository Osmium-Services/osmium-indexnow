<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * IndexNow's tool for the /mcp endpoint, offered through core's `mcp.tools` hook: whether pages are reaching the
 * search engines. Read only. It never returns a page address or the key (the key is public by design, but a
 * set or not set answer is all anyone needs), only counts, codes and the plain-words answer for each code.
 */
class IndexNowMcp
{
    private const FAILURE_LIMIT = 10;

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> tool descriptors
     */
    public static function tools(array $payload): array
    {
        return [
            [
                'name' => 'indexnow_status',
                'description' => 'Whether IndexNow is telling search engines about new and changed pages: on or off, whether this is the live site (nothing is sent from dev or staging), whether the key is set and its file is in the web root, when everything was last sent, submissions per day for the last 7 days (accepted against failed, with how many pages), how many came from each reason, when the last accepted submission was, and the newest 10 failures with the answer code and what it means. The log keeps only 30 days and 200 rows, so older days can be missing. No page addresses and no key. Read only.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => self::class . '::status',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function status(array $call): array
    {
        $settings = IndexNowSettings::get();
        $site = IndexNowSite::fromSite($call['osmium']->config->site);
        $log = new IndexNowLog($call['dataSource']);

        $keyFile = $settings['key'] !== '' && \is_file(IndexNowKeyFile::path((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), $settings['key']));

        return self::shape($settings, $site->isLive, $keyFile, $log->summaryByDay(), $log->summaryByReason(), $log->recentFailures(self::FAILURE_LIMIT), $log->overview());
    }

    /**
     * @param array{enabled: bool, key: string, lastSubmitAll: int, lastSubmitAllCount: int} $settings
     * @param array<int, array<string, mixed>> $days from log-summary-by-day.sql
     * @param array<int, array<string, mixed>> $reasons from log-summary-by-reason.sql
     * @param array<int, array<string, mixed>> $failures from log-recent-failures.sql
     * @param array<string, mixed> $overview from log-overview.sql
     * @return array<string, mixed>
     */
    public static function shape(array $settings, bool $isLive, bool $keyFileExists, array $days, array $reasons, array $failures, array $overview): array
    {
        $status = IndexNowStatus::describe($settings, $isLive);

        return [
            'state' => [
                'enabled' => $settings['enabled'],
                'live_site' => $isLive,
                'summary' => $status['text'],
                'key_set' => $settings['key'] !== '',
                'key_file_present' => $keyFileExists,
                'last_send_all' => $settings['lastSubmitAll'] > 0 ? \date('Y-m-d H:i:s', $settings['lastSubmitAll']) : null,
                'last_send_all_pages' => $settings['lastSubmitAllCount'],
            ],
            'last_7_days' => \array_map(static fn(array $d): array => [
                'day' => (string) $d['day'],
                'accepted' => (int) $d['accepted'],
                'failed' => (int) $d['failed'],
                'pages_accepted' => (int) $d['accepted_urls'],
            ], $days),
            'by_reason' => \array_map(static fn(array $r): array => ['reason' => (string) $r['reason'], 'submissions' => (int) $r['submissions']], $reasons),
            'last_accepted_at' => $overview['last_accepted'] ?? null,
            'log' => ['rows_kept' => (int) ($overview['rows_kept'] ?? 0), 'oldest' => $overview['oldest'] ?? null],
            'recent_failures' => \array_map(static fn(array $f): array => [
                'at' => (string) $f['sent_at'],
                'reason' => (string) $f['reason'],
                'http_code' => (int) $f['http_code'],
                'meaning' => (string) $f['detail'],
                'pages' => (int) $f['url_count'],
            ], $failures),
        ];
    }
}
