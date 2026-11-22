<?php

return [
    // O1 must supply reviewed storage/capacity/scanner adapters and limits.
    // Enabling this flag alone cannot bypass the fail-closed default adapters.
    'uploads_enabled' => (bool) env('DOCUMENT_UPLOADS_ENABLED', false),
    'max_file_bytes' => (int) env('DOCUMENT_MAX_FILE_BYTES', 0),
    'tenant_limit_bytes' => (int) env('DOCUMENT_TENANT_LIMIT_BYTES', 0),
    'app_limit_bytes' => (int) env('DOCUMENT_APP_LIMIT_BYTES', 0),
    'account_reserve_bytes' => (int) env('DOCUMENT_ACCOUNT_RESERVE_BYTES', 0),
    'account_reserve_inodes' => (int) env('DOCUMENT_ACCOUNT_RESERVE_INODES', 0),
    'capacity_max_age_seconds' => 60,
    // O1 must list any additional public roots sharing the hosting account.
    'public_roots' => [],
    'types' => ['application/pdf' => 'pdf', 'text/plain' => 'txt'],
];
