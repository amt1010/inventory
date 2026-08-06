<?php

return [
    // How long an incomplete import can go without progress before it's
    // considered stuck. Drives both the /admin/sellers UI banner and the
    // monitor job's email alert, and doubles as the monitor job's
    // re-check interval — one knob, not two, on purpose.
    'stuck_after_minutes' => env('IMPORT_STUCK_THRESHOLD_MINUTES', 15),

    'notification_email' => env('IMPORT_NOTIFICATION_EMAIL', env('RFQ_NOTIFICATION_EMAIL', 'sales@example.com')),
];
