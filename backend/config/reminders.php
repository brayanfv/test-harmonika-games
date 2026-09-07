<?php

return [
    'due_soon_days' => (int) env('REMINDER_DUE_SOON_DAYS', 3),
    'queue' => env('REMINDER_QUEUE', 'reminders'),
    'chunk_size' => (int) env('REMINDER_CHUNK_SIZE', 100),
    'stale_after' => (int) env('REMINDER_STALE_AFTER', 900),
];
