<?php

return [
    'upload' => [
        'max_kilobytes' => (int) env('STORVIA_UPLOAD_MAX_KILOBYTES', 102400),
        'max_files_per_batch' => (int) env('STORVIA_UPLOAD_MAX_FILES_PER_BATCH', 10),
    ],
];
