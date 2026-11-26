<?php

namespace Tests\Unit\Analysis;

use App\Services\Advisor\RuleBasedAdvisor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RuleBasedAdvisorTest extends TestCase
{
    public function test_unknown_long_keys_produce_recommendation_keys_within_column_limit(): void
    {
        $ref = new ReflectionMethod(RuleBasedAdvisor::class, 'topic');
        $ref->setAccessible(true);
        $advisor = new RuleBasedAdvisor();

        $longKey = 'very_long_predicの歴史tive_insight_key_with_many_segments_even_longer_than_sixty_characters_surely';
        $this->assertGreaterThan(60, mb_strlen($longKey));

        $topic = $ref->invoke($advisor, $longKey, 'Unmatched placeholder subject');
        $this->assertStringStartsWith('other_', $topic);
        $this->assertLessThanOrEqual(60, mb_strlen($topic));
    }

    public function test_recommendation_consolidation_clamps_keys_to_column_limit(): void
    {
        $ref = new ReflectionMethod(RuleBasedAdvisor::class, 'consolidate');
        $ref->setAccessible(true);
        $advisor = new RuleBasedAdvisor();

        $longKey = 'other_'.str_repeat('a', 70);
        $grouped = [
            $longKey => [
                'items' => [[
                    'key' => $longKey,
                    'module' => 'test_module',
                    'title' => 'Long key finding',
                    'priority' => 'high',
                    'evidence' => [],
                ]],
                'priority' => 'high',
            ],
        ];

        $recommendations = $ref->invoke($advisor, $grouped, []);
        $this->assertNotEmpty($recommendations);
        $this->assertLessThanOrEqual(60, mb_strlen($recommendations[0]['recommendation_key']));
    }
}
