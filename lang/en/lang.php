<?php

return [
    'plugin' => [
        'name' => 'Sitemap Robots Humans',
        'description' => 'Automatically generates sitemap.xml, robots.txt, and humans.txt files for your website.'
    ],
    'settings' => [
        'label' => 'Sitemap, Robots, Humans',
        'description' => 'Manage the sitemap, robots, and humans settings.',
        'sitemap' => [
            'enable' => 'Enable sitemap',
            'enable_desc' => 'When checked, a sitemap.xml will automatically be generated.',
        ],
        'robots' => [
            'enable' => 'Enable robots',
            'enable_desc' => 'When checked, a robots.txt file will be generated using the content provided.',
            'content' => 'Robots content',
            'content_placeholder' => 'Enter robots.txt content here...'
        ],
        'humans' => [
            'enable' => 'Enable humans',
            'enable_desc' => 'When checked, a humans.txt file will be generated using the content provided.',
            'content' => 'Humans content',
            'content_placeholder' => 'Enter humans.txt content here...'
        ]
    ],
    'permissions' => [
        'access_settings' => 'Manage sitemap, robots, and humans settings'
    ]
];