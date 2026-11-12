<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

use Osmium\Core\Library\FileSystem;
use Osmium\Core\OsmiumConfigLoader;

/**
 * What a ping needs to know about the site: its address, whether it is the live site, and the address form pages use.
 * Hook handlers get no config, so this reads the site's own config file.
 */
class IndexNowSite
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly bool $isLive,
        public readonly bool $trailingSlash,
    ) {}

    public static function load(string $configPath = 'app/config'): self
    {
        $loader = new OsmiumConfigLoader(fileSystem: new FileSystem(), configPath: $configPath);
        $site = $loader->load()->site;

        return self::fromSite($site);
    }

    public static function fromSite(object $site): self
    {
        $environment = (string) ($site->environment ?? 'dev');

        return new self(
            baseUrl: \rtrim((string) $site->FQDN, '/'),
            isLive: !\in_array($environment, ['dev', 'staging'], true),
            trailingSlash: (bool) ($site->urlTrailingSlash ?? false),
        );
    }

    public function host(): string
    {
        return (string) \parse_url($this->baseUrl, PHP_URL_HOST);
    }

    /** A page's stored address ('' is the home page) as the full URL visitors use */
    public function pageUrl(string $slug): string
    {
        $slug = \trim($slug, '/');
        $isHome = $slug === '';
        if ($isHome) return $this->baseUrl . '/';

        return $this->baseUrl . '/' . $slug . ($this->trailingSlash ? '/' : '');
    }
}
