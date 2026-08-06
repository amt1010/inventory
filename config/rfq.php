<?php

return [
    'notification_email' => env('RFQ_NOTIFICATION_EMAIL', 'sales@example.com'),

    'reasons' => [
        'Request a Quote' => 'Request a Quote',
        'General Inquiry' => 'General Inquiry',
    ],

    'contact_preferences' => [
        'email' => 'Email',
        'phone' => 'Phone',
    ],
];
