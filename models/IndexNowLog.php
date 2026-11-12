<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * What was sent and what IndexNow answered, kept for 90 days.
 */
class IndexNowLog extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'indexnow_log');
    }

    /** @param string[] $urls one row per URL, all sharing the answer */
    public function record(array $urls, string $reason, int $httpCode): void
    {
        $detail = IndexNowClient::describe($httpCode);
        foreach ($urls as $url) {
            $this->database->prepare(
                sql: $this->loadSqlFile('log-insert.sql'),
                bindings: [':url' => $url, ':reason' => $reason, ':http_code' => $httpCode, ':detail' => $detail],
            )->execute();
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 200): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('log-recent.sql'), bindings: [':limit' => $limit])->resultset();
    }

    public function prune(): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('log-prune.sql'))->execute();
    }
}
