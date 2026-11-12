<?php

declare(strict_types=1);

/**
 * IndexNow's recent submissions, rendered by Osmium\Modules\Admin\Core\ListView. One row per submission (its first URL and how many), from IndexNowLog::recent().
 */
return [
    'log' => [
        'id' => 'indexnow-log',
        'title' => 'Recent Submissions',
        'icon' => 'bx-history',
        'iconClass' => 'text-primary',
        'noun' => ['submission', 'submissions'],
        'emptyText' => 'Nothing sent yet',
        'columns' => [
            ['key' => 'sent_at', 'label' => 'Sent', 'type' => 'text', 'sortKey' => 'id', 'nowrap' => true],
            ['key' => 'url', 'label' => 'URL', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'url_count', 'label' => 'URLs', 'type' => 'text', 'align' => 'end', 'nowrap' => true],
            ['key' => 'reason', 'label' => 'Why', 'type' => 'text', 'nowrap' => true, 'hideBelow' => 'md'],
            ['key' => 'detail', 'label' => 'Answer', 'type' => 'text', 'nowrap' => true],
        ],
        'actions' => [],
    ],
];
