<?php

declare(strict_types=1);

namespace Tests\Services\IndexNow;

use Osmium\Services\IndexNow\Models\IndexNowLogRows;
use Osmium\Services\IndexNow\Models\IndexNowStatus;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/IndexNowStatus.php';
require_once __DIR__ . '/../models/IndexNowLogRows.php';

class PresentationTest extends TestCase
{
    private function settings(bool $enabled, int $last = 0, int $count = 0): array
    {
        return ['enabled' => $enabled, 'lastSubmitAll' => $last, 'lastSubmitAllCount' => $count];
    }

    public function test_status_when_off(): void
    {
        $status = IndexNowStatus::describe($this->settings(false), true);

        $this->assertSame('Off', $status['badge']);
        $this->assertStringContainsString('Turn it on', $status['text']);
    }

    public function test_status_when_on_but_not_the_live_site(): void
    {
        $status = IndexNowStatus::describe($this->settings(true), false);

        $this->assertSame('bg-label-info', $status['badgeClass']);
        $this->assertStringContainsString('not the live site', $status['text']);
    }

    public function test_status_when_on_and_pages_were_sent(): void
    {
        $status = IndexNowStatus::describe($this->settings(true, (int) \strtotime('2026-11-12 12:39:00'), 37), true);

        $this->assertSame('bg-label-success', $status['badgeClass']);
        $this->assertStringStartsWith('37 pages sent on 12 Nov 2026.', $status['text']);
        $this->assertStringContainsString('sent automatically', $status['text']);
    }

    public function test_status_when_on_live_but_nothing_sent_yet_points_to_advanced(): void
    {
        $status = IndexNowStatus::describe($this->settings(true), true);

        $this->assertStringContainsString('No pages have been sent yet', $status['text']);
        $this->assertStringContainsString('Advanced', $status['text']);
    }

    public function test_log_rows_read_in_plain_english(): void
    {
        $rows = [
            ['id' => 3, 'url' => 'https://x.test/blog/', 'url_count' => 37, 'reason' => 'all pages', 'detail' => 'Accepted', 'sent_at' => '2026-11-12 12:39:04'],
            ['id' => 2, 'url' => 'https://x.test/about', 'url_count' => 1, 'reason' => 'saved', 'detail' => 'Accepted', 'sent_at' => '2026-11-12 12:30:00'],
            ['id' => 1, 'url' => 'https://x.test/old', 'url_count' => 1, 'reason' => 'removed', 'detail' => 'Could not reach IndexNow', 'sent_at' => '2026-11-12 12:00:00'],
        ];

        $presented = IndexNowLogRows::present($rows);

        $this->assertSame(['Sent 37 pages', 'Sent /about', 'Told it /old was removed'], \array_column($presented, 'what'));
        $this->assertSame('12 Nov 12:39', $presented[0]['when']);
        $this->assertSame('Could not reach IndexNow', $presented[2]['answer']);
    }

    public function test_a_home_page_row_reads_as_a_slash(): void
    {
        $presented = IndexNowLogRows::present([
            ['id' => 1, 'url' => 'https://x.test', 'url_count' => 1, 'reason' => 'saved', 'detail' => 'Accepted', 'sent_at' => '2026-11-12 12:00:00'],
        ]);

        $this->assertSame('Sent /', $presented[0]['what']);
    }
}
