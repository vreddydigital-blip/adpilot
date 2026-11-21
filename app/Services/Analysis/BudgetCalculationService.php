<?php

namespace App\Services\Analysis;

class BudgetCalculationService
{
    public const VERSION = 'v1.0';

    public const CHANNELS = [
        'search' => ['label' => 'Search ads', 'cpc_factor' => 1.0, 'rate_factor' => 1.0],
        'social' => ['label' => 'Social ads', 'cpc_factor' => 0.6, 'rate_factor' => 0.8],
        'display' => ['label' => 'Display ads', 'cpc_factor' => 0.4, 'rate_factor' => 0.6],
    ];

    private const SCENARIOS = [
        'conservative' => ['label' => 'Conservative case', 'cpc_factor' => 1.1, 'rate_factor' => 0.7],
        'expected' => ['label' => 'Expected case', 'cpc_factor' => 1.0, 'rate_factor' => 1.0],
        'best' => ['label' => 'Best case', 'cpc_factor' => 0.9, 'rate_factor' => 1.3],
    ];

    public function scenarios(float $budget, float $cpc, float $leadRate, float $conversionRate, float $aov, float $margin, ?float $targetCpa = null): array
    {
        return collect(self::SCENARIOS)
            ->map(fn (array $scenario) => [
                'scenario' => $scenario['label'],
                ...$this->simulate(
                    $budget,
                    $cpc * $scenario['cpc_factor'],
                    $leadRate * $scenario['rate_factor'],
                    $conversionRate * $scenario['rate_factor'],
                    $aov,
                    $margin,
                    $targetCpa,
                ),
            ])
            ->values()
            ->all();
    }

    public function simulate(float $budget, float $cpc, float $leadRatePercent, float $conversionRatePercent, float $aov, float $marginPercent, ?float $targetCpa = null): array
    {
        $clicks = $cpc > 0 ? $budget / $cpc : 0.0;
        $leads = $clicks * ($leadRatePercent / 100);
        $conversions = $leads * ($conversionRatePercent / 100);
        $cpa = $conversions > 0 ? $budget / $conversions : null;
        $revenue = $conversions * $aov;
        $margin = $marginPercent / 100;
        $breakEvenRoas = $margin > 0 ? 1 / $margin : null;
        $profit = $revenue - $budget;
        $roas = $budget > 0 ? $revenue / $budget : null;

        return [
            'budget' => round($budget, 2),
            'assumed_cpc' => round($cpc, 2),
            'assumed_lead_rate' => round($leadRatePercent, 2),
            'assumed_conversion_rate' => round($conversionRatePercent, 2),
            'average_order_value' => round($aov, 2),
            'gross_margin' => round($marginPercent, 2),
            'estimated_clicks' => round($clicks, 2),
            'estimated_leads' => round($leads, 2),
            'estimated_conversions' => round($conversions, 2),
            'estimated_cpa' => $cpa === null ? null : round($cpa, 2),
            'estimated_revenue' => round($revenue, 2),
            'estimated_roas' => $roas === null ? null : round($roas, 2),
            'estimated_profit' => round($profit, 2),
            'break_even_roas' => $breakEvenRoas === null ? null : round($breakEvenRoas, 2),
            'meets_target_cpa' => $targetCpa === null ? null : ($cpa !== null && $cpa <= $targetCpa),
            'meets_break_even_roas' => ($roas !== null && $breakEvenRoas !== null) ? $roas >= $breakEvenRoas : null,
            'disclaimer' => 'Deterministic simulation from your stated assumptions - not actual campaign performance.',
        ];
    }

    public function compare(float $budget, float $baseCpc, float $leadRate, float $conversionRate, float $aov, float $marginPercent): array
    {
        return collect(self::CHANNELS)
            ->map(fn (array $channel, string $key) => [
                'channel' => $key,
                'label' => $channel['label'],
                'assumptions' => 'CPC factor '.$channel['cpc_factor'].', conversion-rate factor '.$channel['rate_factor'],
                ...$this->simulate(
                    $budget,
                    $baseCpc * $channel['cpc_factor'],
                    $leadRate * $channel['rate_factor'],
                    $conversionRate * $channel['rate_factor'],
                    $aov,
                    $marginPercent,
                ),
            ])
            ->values()
            ->all();
    }
}
