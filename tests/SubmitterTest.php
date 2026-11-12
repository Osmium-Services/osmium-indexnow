<?php

declare(strict_types=1);

namespace Tests\Services\IndexNow;

use Osmium\Services\IndexNow\Models\IndexNowClient;
use Osmium\Services\IndexNow\Models\IndexNowHooks;
use Osmium\Services\IndexNow\Models\IndexNowLog;
use Osmium\Services\IndexNow\Models\IndexNowSite;
use Osmium\Services\IndexNow\Models\IndexNowSubmitter;
use Tests\Integration\DatabaseTestCase;

foreach (['Settings', 'KeyFile', 'Client', 'Site', 'Log', 'Submitter', 'Hooks'] as $model) {
    require_once __DIR__ . "/../models/IndexNow{$model}.php";
}

/**
 * The submitter against the dev database with IndexNow's endpoint faked (no network).
 */
class SubmitterTest extends DatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = \sys_get_temp_dir() . '/indexnow-sub-' . \uniqid();
        \mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->root . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->root);
        parent::tearDown();
    }

    private function fakeClient(int $answer): object
    {
        return new class ($answer) extends IndexNowClient {
            public array $sent = [];

            public function __construct(private int $answer) {}

            public function send(array $payload): int
            {
                $this->sent[] = $payload;
                return $this->answer;
            }
        };
    }

    private function submitter(object $client, bool $live = true, bool $enabled = true): IndexNowSubmitter
    {
        return new IndexNowSubmitter(
            site: new IndexNowSite('https://www.example.com', $live, false),
            log: new IndexNowLog($this->db),
            client: $client,
            documentRoot: $this->root,
            settings: ['enabled' => $enabled, 'key' => 'abcd1234abcd1234'],
        );
    }

    public function test_a_live_submission_is_sent_logged_and_writes_the_key_file(): void
    {
        $client = $this->fakeClient(200);

        $code = $this->submitter($client)->submit(['https://www.example.com/a', 'https://www.example.com/b'], 'saved');

        $this->assertSame(200, $code);
        $this->assertSame('www.example.com', $client->sent[0]['host']);
        $this->assertSame('https://www.example.com/abcd1234abcd1234.txt', $client->sent[0]['keyLocation']);
        $this->assertSame('abcd1234abcd1234', \file_get_contents($this->root . '/abcd1234abcd1234.txt'));

        $rows = (new IndexNowLog($this->db))->recent(1);
        $this->assertSame('https://www.example.com/a', $rows[0]['url']);
        $this->assertSame(2, (int) $rows[0]['url_count']);
        $this->assertSame('Accepted', $rows[0]['detail']);
        $this->assertSame('saved', $rows[0]['reason']);
    }

    public function test_nothing_is_sent_when_off_or_not_live_or_empty(): void
    {
        $client = $this->fakeClient(200);

        $this->assertSame(0, $this->submitter($client, enabled: false)->submit(['https://www.example.com/a'], 'saved'));
        $this->assertSame(0, $this->submitter($client, live: false)->submit(['https://www.example.com/a'], 'saved'));
        $this->assertSame(0, $this->submitter($client)->submit([], 'saved'));
        $this->assertSame([], $client->sent);
    }

    public function test_a_refusal_is_logged_with_its_meaning(): void
    {
        $this->submitter($this->fakeClient(403))->submit(['https://www.example.com/a'], 'removed');

        $row = (new IndexNowLog($this->db))->recent(1)[0];
        $this->assertSame(403, (int) $row['http_code']);
        $this->assertSame('Key not valid or key file not found', $row['detail']);
    }

    public function test_a_failing_client_never_throws_into_the_caller(): void
    {
        $client = new class extends IndexNowClient {
            public function send(array $payload): int
            {
                throw new \RuntimeException('network down');
            }
        };

        $this->assertSame(0, $this->submitter($client)->submit(['https://www.example.com/a'], 'saved'));
    }

    public function test_the_log_keeps_only_the_newest_200_rows(): void
    {
        $log = new IndexNowLog($this->db);
        for ($i = 0; $i < 205; $i++) {
            $log->record(["https://www.example.com/p{$i}"], 'saved', 200);
        }

        $log->prune();

        $rows = $log->recent(500);
        $this->assertCount(200, $rows);
        $this->assertSame('https://www.example.com/p204', $rows[0]['url']);
    }

    public function test_the_hook_does_nothing_without_a_data_source(): void
    {
        IndexNowHooks::pageSaved(['url' => 'about', 'robots' => 'index,follow']);
        IndexNowHooks::pageRemoved(['url' => 'about']);

        $this->assertTrue(true); // no error is the point
    }
}
