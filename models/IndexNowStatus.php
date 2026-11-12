<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Models;

/**
 * The status line at the top of the IndexNow page, in plain words: a badge and one sentence.
 */
class IndexNowStatus
{
    /** @param array{enabled: bool, lastSubmitAll: int, lastSubmitAllCount: int} $settings @return array{badge: string, badgeClass: string, text: string} */
    public static function describe(array $settings, bool $isLive): array
    {
        if (!$settings['enabled']) {
            return [
                'badge' => 'Off',
                'badgeClass' => 'bg-label-secondary',
                'text' => 'Turn it on and Bing and other search engines are told about new and changed pages straight away.',
            ];
        }

        if (!$isLive) {
            return [
                'badge' => 'On',
                'badgeClass' => 'bg-label-info',
                'text' => 'This is not the live site, so nothing is sent from here. The live site sends once it is turned on there.',
            ];
        }

        return ['badge' => 'On', 'badgeClass' => 'bg-label-success', 'text' => self::liveText($settings)];
    }

    /** @param array{lastSubmitAll: int, lastSubmitAllCount: int} $settings */
    private static function liveText(array $settings): string
    {
        $neverSent = $settings['lastSubmitAll'] === 0;
        if ($neverSent) return 'No pages have been sent yet. Open Advanced and use Send all pages.';

        $count = $settings['lastSubmitAllCount'];
        $what = $count > 0 ? $count . ' pages' : 'Pages'; // Releases before 0.3.0 saved the date but not the count

        return $what . ' sent on ' . \date('j M Y', $settings['lastSubmitAll']) . '. New and edited pages are sent automatically.';
    }
}
