<?php

declare(strict_types=1);

namespace Tests\Services\IndexNow;

use Osmium\Services\IndexNow\Models\IndexNowClient;
use Osmium\Services\IndexNow\Models\IndexNowKeyFile;
use Osmium\Services\IndexNow\Models\IndexNowSettings;
use Osmium\Services\IndexNow\Models\IndexNowSite;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/IndexNowSettings.php';
require_once __DIR__ . '/../models/IndexNowKeyFile.php';
require_once __DIR__ . '/../models/IndexNowClient.php';
require_once __DIR__ . '/../models/IndexNowSite.php';

class IndexNowTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = \sys_get_temp_dir() . '/indexnow-test-' . \uniqid();
        \mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->dir . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->dir);
    }

    public function test_settings_default_to_off_with_no_key(): void
    {
        $this->assertSame(['enabled' => false, 'key' => '', 'lastSubmitAll' => 0], IndexNowSettings::get($this->dir . '/none.json.php'));
    }

    public function test_settings_round_trip_through_the_php_guarded_file(): void
    {
        $path = $this->dir . '/settings.json.php';
        $key = IndexNowSettings::newKey();

        IndexNowSettings::save(enabled: true, key: $key, lastSubmitAll: 1700000000, path: $path);

        $this->assertSame(['enabled' => true, 'key' => $key, 'lastSubmitAll' => 1700000000], IndexNowSettings::get($path));
        $this->assertStringStartsWith('<?php exit(); ?>', (string) \file_get_contents($path));
    }

    public function test_a_bad_key_in_the_file_reads_as_off(): void
    {
        $path = $this->dir . '/bad.json.php';
        IndexNowSettings::save(enabled: true, key: 'x/../y', path: $path);

        $this->assertSame(['enabled' => false, 'key' => '', 'lastSubmitAll' => 0], IndexNowSettings::get($path));
    }

    public function test_submit_all_waits_a_day_after_use(): void
    {
        $this->assertSame(0, IndexNowSettings::submitAllWait(0, 1700000000));
        $this->assertSame(86400, IndexNowSettings::submitAllWait(1700000000, 1700000000));
        $this->assertSame(3600, IndexNowSettings::submitAllWait(1700000000, 1700000000 + 86400 - 3600));
        $this->assertSame(0, IndexNowSettings::submitAllWait(1700000000, 1700000000 + 86400));
    }

    public function test_key_rules(): void
    {
        $this->assertTrue(IndexNowSettings::isValidKey(IndexNowSettings::newKey()));
        $this->assertTrue(IndexNowSettings::isValidKey('abcd-1234'));
        foreach (['', 'short', '../etc/passwd', 'has space 12345', \str_repeat('a', 129)] as $bad) {
            $this->assertFalse(IndexNowSettings::isValidKey($bad), $bad);
        }
    }

    public function test_the_key_file_is_written_replaced_and_removed(): void
    {
        $key = 'abcd1234abcd1234';
        $path = IndexNowKeyFile::path($this->dir, $key);

        $this->assertTrue(IndexNowKeyFile::ensure($this->dir, $key));
        $this->assertSame($key, \file_get_contents($path));

        \file_put_contents($path, 'tampered');
        $this->assertTrue(IndexNowKeyFile::ensure($this->dir, $key));
        $this->assertSame($key, \file_get_contents($path));

        IndexNowKeyFile::remove($this->dir, $key);
        $this->assertFileDoesNotExist($path);
    }

    public function test_an_invalid_key_never_touches_the_web_root(): void
    {
        $this->assertFalse(IndexNowKeyFile::ensure($this->dir, '../evil'));
        IndexNowKeyFile::remove($this->dir, '../evil');
        $this->assertSame([], \glob($this->dir . '/*'));
    }

    public function test_page_urls_follow_the_sites_slash_form(): void
    {
        $plain = new IndexNowSite('https://www.example.com', true, false);
        $slashed = new IndexNowSite('https://www.example.com', true, true);

        $this->assertSame('https://www.example.com/', $plain->pageUrl(''));
        $this->assertSame('https://www.example.com/about', $plain->pageUrl('about'));
        $this->assertSame('https://www.example.com/about/', $slashed->pageUrl('/about/'));
        $this->assertSame('www.example.com', $plain->host());
    }

    public function test_only_a_live_site_counts_as_live(): void
    {
        foreach (['dev' => false, 'staging' => false, 'live' => true] as $environment => $expected) {
            $site = IndexNowSite::fromSite((object) ['FQDN' => 'https://x.test', 'environment' => $environment]);
            $this->assertSame($expected, $site->isLive, $environment);
        }
    }

    public function test_payload_is_deduplicated_and_capped(): void
    {
        $urls = \array_map(fn(int $i): string => "https://x.test/p{$i}", \range(1, 10005));
        $urls[] = 'https://x.test/p1';

        $payload = IndexNowClient::payload('x.test', 'abcd1234', 'https://x.test/abcd1234.txt', $urls);

        $this->assertCount(IndexNowClient::MAX_URLS, $payload['urlList']);
        $this->assertSame('x.test', $payload['host']);
        $this->assertSame('https://x.test/abcd1234.txt', $payload['keyLocation']);
    }

    public function test_answers_are_described_in_words(): void
    {
        $this->assertSame('Accepted', IndexNowClient::describe(200));
        $this->assertSame('Key not valid or key file not found', IndexNowClient::describe(403));
        $this->assertSame('Could not reach IndexNow', IndexNowClient::describe(0));
        $this->assertSame('Unexpected answer', IndexNowClient::describe(500));
    }
}
