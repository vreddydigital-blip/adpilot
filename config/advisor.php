<?php

return [
    'engine' => env('ADVISOR_ENGINE', 'rule_based'),

    'engines' => [
        'rule_based' => \App\Services\Advisor\RuleBasedAdvisor::class,
    ],
];
