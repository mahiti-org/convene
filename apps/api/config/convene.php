<?php

// Hosting-mode branching lives here; read config('convene.*'), never ad hoc env() checks.

return [
    // 'vm' (Docker Compose, SSH+root) or 'shared' (cPanel/Plesk, no root, no Docker)
    'hosting_mode' => env('CONVENE_HOSTING_MODE', 'vm'),

    // Filesystem disk selected per hosting mode: 's3_storage' on VM mode,
    // 'local_storage' on shared hosting.
    'default_disk' => env('CONVENE_DEFAULT_DISK', 'local_storage'),

    // 'worker' (persistent queue:work container), 'sync' (no queue at all), or
    // 'cron-batch' (cron-triggered queue:work --stop-when-empty on shared hosting).
    'queue_mode' => env('CONVENE_QUEUE_MODE', 'sync'),
];
