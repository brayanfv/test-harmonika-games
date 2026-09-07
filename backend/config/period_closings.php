<?php

return [
    'queue' => env('PERIOD_CLOSING_QUEUE', 'period-closings'),
    'chunk_size' => (int) env('PERIOD_CLOSING_CHUNK_SIZE', 500),
    'stale_after' => (int) env('PERIOD_CLOSING_STALE_AFTER', 1200),
];
