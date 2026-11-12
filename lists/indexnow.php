<?php

declare(strict_types=1);

/**
 * IndexNow's recent submissions, rendered by Osmium\Modules\Admin\Core\ListView. Rows come from IndexNowLog::recent().
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
            ['key' => 'reason', 'label' => 'Why', 'type' => 'text', 'nowrap' => true, 'hideBelow' => 'md'],
            ['key' => 'detail', 'label' => 'Answer', 'type' => 'text', 'nowrap' => true],
        ],
        'actions' => [],
    ],
];
