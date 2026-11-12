<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * What was sent and what IndexNow answered: one row per submission, kept 30 days and at most 200 rows.
 */
class IndexNowLog extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'indexnow_log');
    }

    /** @param string[] $urls the submission's URLs: the row keeps the first and how many there were */
    public function record(array $urls, string $reason, int $httpCode): void
    {
        $this->database->prepare(
            sql: $this->loadSqlFile('log-insert.sql'),
            bindings: [
                ':url' => (string) ($urls[0] ?? ''),
                ':url_count' => \count($urls),
                ':reason' => $reason,
                ':http_code' => $httpCode,
                ':detail' => IndexNowClient::describe($httpCode),
            ],
        )->execute();
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 200): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('log-recent.sql'), bindings: [':limit' => $limit])->resultset();
    }

    /** True when this URL was sent alone for this reason and accepted in the last 10 minutes */
    public function recentlySent(string $url, string $reason): bool
    {
        $row = $this->database->prepare(
            sql: $this->loadSqlFile('log-recently-sent.sql'),
            bindings: [':url' => $url, ':reason' => $reason],
        )->single();

        return !empty($row);
    }

    public function prune(): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('log-prune-age.sql'))->execute();
        $this->database->prepare(sql: $this->loadSqlFile('log-prune-cap.sql'))->execute();
    }
}
