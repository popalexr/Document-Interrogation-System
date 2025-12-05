<?php

return [
    'allowed_mime_types' => [
        'pdf','doc','docx', // Document files
        'txt','md','json', // Text files
        'html','htm','tex', // Markup files
        'pptx', // Presentation files
    ],
    'delete_after_days' => 30, // Number of days after which deleted documents are cleaned up
];