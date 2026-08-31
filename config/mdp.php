<?php

declare(strict_types=1);

/*
 * Application configuration that is deployment-level rather than
 * business-level. Business rules that operations staff may retune at runtime
 * live in the system_settings table, not here.
 */
return [

    'attachments' => [
        'disk' => env('PRIVATE_FILESYSTEM_DISK', 'private'),
        'directory' => 'problem-attachments',
        'max_kilobytes' => (int) env('MDP_ATTACHMENT_MAX_KB', 5120),
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'xls', 'doc', 'docx'],
    ],

    'dashboard' => [
        // Seconds to cache dashboard aggregates; 0 disables caching.
        'cache_ttl' => (int) env('MDP_DASHBOARD_CACHE_TTL', 300),
        'trend_months' => 6,
        'supplier_ranking_limit' => 5,
        'recent_delivery_limit' => 10,
    ],

];
