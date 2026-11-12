<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * Turns log rows into the plain-English rows the list shows: when, what was sent, and how IndexNow answered.
 */
class IndexNowLogRows
{
    /**
     * @param array<int, array<string, mixed>> $rows from IndexNowLog::recent()
     * @return array<int, array{id: int, when: string, what: string, answer: string}>
     */
    public static function present(array $rows): array
    {
        $presented = [];
        foreach ($rows as $row) {
            $presented[] = [
                'id' => (int) $row['id'],
                'when' => \date('j M H:i', (int) \strtotime((string) $row['sent_at'])),
                'what' => self::what($row),
                'answer' => (string) $row['detail'],
            ];
        }

        return $presented;
    }

    /** @param array<string, mixed> $row */
    private static function what(array $row): string
    {
        $count = (int) $row['url_count'];
        $isBatch = $count > 1;
        if ($isBatch) return 'Sent ' . $count . ' pages';

        $path = (string) (\parse_url((string) $row['url'], PHP_URL_PATH) ?: '/');
        $verb = $row['reason'] === 'removed' ? 'Told it ' : 'Sent ';

        return $verb . $path . ($row['reason'] === 'removed' ? ' was removed' : '');
    }
}
