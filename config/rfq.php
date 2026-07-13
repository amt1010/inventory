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

    'countries' => [
        'India' => 'India',
        'United States' => 'United States',
        'United Kingdom' => 'United Kingdom',
        'United Arab Emirates' => 'United Arab Emirates',
        'Singapore' => 'Singapore',
        'Australia' => 'Australia',
        'Germany' => 'Germany',
        'Other' => 'Other',
    ],

    'markets' => [
        'Broadband' => 'Broadband',
        'Enterprise' => 'Enterprise',
        'Energy' => 'Energy',
        'Industrial' => 'Industrial',
        'Hyperscale' => 'Hyperscale',
    ],
];
