<?php

declare(strict_types=1);

/**
 * IndexNow's recent submissions, rendered by Osmium\Modules\Admin\Core\ListView. Rows are prepared by IndexNowLogRows.
 */
return [
    'log' => [
        'id' => 'indexnow-log',
        'title' => 'Recent Activity',
        'icon' => 'bx-history',
        'iconClass' => 'text-primary',
        'noun' => ['entry', 'entries'],
        'emptyText' => 'Nothing sent yet',
        'columns' => [
            ['key' => 'when', 'label' => 'When', 'type' => 'text', 'sortKey' => 'id', 'nowrap' => true],
            ['key' => 'what', 'label' => 'What', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'answer', 'label' => 'Answer', 'type' => 'text', 'nowrap' => true],
        ],
        'actions' => [],
    ],
];
