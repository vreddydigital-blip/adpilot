<?php

namespace Tests\Unit\Analysis;

use App\Services\Analysis\BudgetCalculationService;
use PHPUnit\Framework\TestCase;

class BudgetCalculationServiceTest extends TestCase
{
    private BudgetCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BudgetCalculationService();
    }

    public function test_simulate_computes_clicks_leads_conversions_and_cpa(): void
    {
        $result = $this->service->simulate(1000, 2, 4, 10, 300, 40, 500);

        $this->assertSame(500.0, $result['estimated_clicks']);
        $this->assertSame(20.0, $result['estimated_leads']);
        $this->assertSame(2.0, $result['estimated_conversions']);
        $this->assertSame(500.0, $result['estimated_cpa']);
        $this->assertSame(600.0, $result['estimated_revenue']);
        $this->assertSame(-400.0, $result['estimated_profit']);
    }

    public function test_simulate_computes_roas_and_break_even_roas(): void
    {
        $result = $this->service->simulate(1000, 2, 4, 10, 300, 40);

        $this->assertSame(2.5, $result['break_even_roas']);
        $this->assertSame(0.6, $result['estimated_roas']);
        $this->assertFalse($result['meets_break_even_roas']);
    }

    public function test_target_cpa_check(): void
    {
        $meeting = $this->service->simulate(1000, 2, 4, 10, 300, 40, 500);
        $missing = $this->service->simulate(1000, 2, 4, 10, 300, 40, 400);

        $this->assertTrue($meeting['meets_target_cpa']);
        $this->assertFalse($missing['meets_target_cpa']);
    }

    public function test_cpa_is_null_when_no_conversions(): void
    {
        $result = $this->service->simulate(1000, 2, 0, 10, 300, 40);

        $this->assertNull($result['estimated_cpa']);
        $this->assertSame(0.0, $result['estimated_roas']);
        $this->assertSame(0.0, $result['estimated_leads']);
        $this->assertNull($result['meets_target_cpa']);
    }

    public function test_scenarios_returns_three_labeled_cases(): void
    {
        $scenarios = $this->service->scenarios(1000, 2, 4, 10, 300, 40);

        $this->assertCount(3, $scenarios);
        $this->assertSame('Conservative case', $scenarios[0]['scenario']);
        $this->assertSame('Expected case', $scenarios[1]['scenario']);
        $this->assertSame('Best case', $scenarios[2]['scenario']);

        [$conservative, $expected, $best] = $scenarios;

        $this->assertSame(454.55, $conservative['estimated_clicks']);
        $this->assertSame(500.0, $expected['estimated_clicks']);
        $this->assertSame(555.56, $best['estimated_clicks']);
        $this->assertGreaterThan($conservative['estimated_leads'], $best['estimated_leads']);
    }

    public function test_scenarios_carry_disclaimer(): void
    {
        foreach ($this->service->scenarios(1000, 2, 4, 10, 300, 40) as $scenario) {
            $this->assertSame('Deterministic simulation from your stated assumptions - not actual campaign performance.', $scenario['disclaimer']);
        }
    }

    public function test_compare_applies_channel_factors(): void
    {
        $comparison = $this->service->compare(1000, 2, 4, 10, 300, 40);

        $this->assertCount(3, $comparison);
        $this->assertSame('search', $comparison[0]['channel']);
        $this->assertSame('social', $comparison[1]['channel']);
        $this->assertSame('display', $comparison[2]['channel']);

        $this->assertSame(500.0, $comparison[0]['estimated_clicks']);
        $this->assertSame(833.33, $comparison[1]['estimated_clicks']);
        $this->assertSame(1250.0, $comparison[2]['estimated_clicks']);
    }
}
