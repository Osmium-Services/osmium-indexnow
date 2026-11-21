<?php

declare(strict_types=1);

namespace Tests\Services\IndexNow;

use Osmium\Services\IndexNow\Models\IndexNowMcp;
use PHPUnit\Framework\TestCase;

foreach (['Settings', 'KeyFile', 'Client', 'Site', 'Status', 'Mcp'] as $model) {
    require_once __DIR__ . "/../models/IndexNow{$model}.php";
}

class IndexNowMcpTest extends TestCase
{
    private const SETTINGS = ['enabled' => true, 'key' => 'abcdef0123456789abcdef0123456789', 'lastSubmitAll' => 1_700_000_000, 'lastSubmitAllCount' => 42];

    public function test_the_tool_is_read_only_and_takes_no_input(): void
    {
        $tools = IndexNowMcp::tools([]);

        $this->assertSame(['indexnow_status'], \array_column($tools, 'name'));
        $this->assertArrayNotHasKey('write', $tools[0]);
    }

    public function test_days_reasons_and_failures_are_shaped_as_numbers(): void
    {
        $out = IndexNowMcp::shape(
            self::SETTINGS, true, true,
            [['day' => '2026-11-20', 'accepted' => '3', 'failed' => '1', 'accepted_urls' => '9']],
            [['reason' => 'saved', 'submissions' => '4']],
            [['sent_at' => '2026-11-20 10:00:00', 'reason' => 'saved', 'http_code' => '403', 'detail' => 'Key not valid or key file not found', 'url_count' => '1']],
            ['rows_kept' => '4', 'oldest' => '2026-11-14 08:00:00', 'last_accepted' => '2026-11-20 09:00:00'],
        );

        $this->assertSame(['day' => '2026-11-20', 'accepted' => 3, 'failed' => 1, 'pages_accepted' => 9], $out['last_7_days'][0]);
        $this->assertSame(403, $out['recent_failures'][0]['http_code']);
        $this->assertSame('Key not valid or key file not found', $out['recent_failures'][0]['meaning']);
        $this->assertSame('2026-11-20 09:00:00', $out['last_accepted_at']);
        $this->assertSame(4, $out['log']['rows_kept']);
        $this->assertSame(42, $out['state']['last_send_all_pages']);
    }

    public function test_the_key_and_page_addresses_never_reach_the_answer(): void
    {
        $out = IndexNowMcp::shape(
            self::SETTINGS, true, true, [], [],
            [['sent_at' => '2026-11-20 10:00:00', 'reason' => 'saved', 'http_code' => 403, 'detail' => 'x', 'url_count' => 1, 'url' => 'https://example.com/secret-page']],
            ['rows_kept' => 1, 'oldest' => null, 'last_accepted' => null],
        );
        $json = \json_encode($out);

        $this->assertStringNotContainsString('abcdef0123456789', $json);
        $this->assertStringNotContainsString('secret-page', $json);
        $this->assertTrue($out['state']['key_set']);
    }

    public function test_an_off_site_with_no_log_gives_an_empty_but_complete_answer(): void
    {
        $out = IndexNowMcp::shape(['enabled' => false, 'key' => '', 'lastSubmitAll' => 0, 'lastSubmitAllCount' => 0], false, false, [], [], [], []);

        $this->assertFalse($out['state']['enabled']);
        $this->assertFalse($out['state']['key_set']);
        $this->assertNull($out['state']['last_send_all']);
        $this->assertNull($out['last_accepted_at']);
        $this->assertSame(0, $out['log']['rows_kept']);
        $this->assertSame([], $out['recent_failures']);
    }
}
