<?php

return [

    'note' => 'No provider connection is live. Saving credentials only stores them encrypted for later use; no advertising, analytics or CRM data is fetched or fabricated.',

    'providers' => [
        'google_ads' => [
            'label' => 'Google Ads',
            'description' => 'Ad spend, CPC, CPA and conversion metrics for real performance measurement.',
            'credentials' => ['customer_id', 'developer_token', 'client_id', 'client_secret', 'refresh_token'],
            'provides' => ['spend', 'cpc', 'cpa', 'conversions'],
        ],
        'meta_ads' => [
            'label' => 'Meta Ads',
            'description' => 'Facebook and Instagram campaign delivery and cost metrics.',
            'credentials' => ['account_id', 'access_token'],
            'provides' => ['spend', 'cpm', 'cpa', 'conversions'],
        ],
        'google_analytics' => [
            'label' => 'Google Analytics 4',
            'description' => 'Real traffic, conversion and revenue data to replace heuristic scoring inputs.',
            'credentials' => ['property_id', 'client_email', 'private_key'],
            'provides' => ['sessions', 'conversions', 'revenue'],
        ],
        'google_search_console' => [
            'label' => 'Google Search Console',
            'description' => 'Organic search queries, clicks and positions.',
            'credentials' => ['property_url'],
            'provides' => ['organic_clicks', 'impressions', 'positions'],
        ],
    ],

];
