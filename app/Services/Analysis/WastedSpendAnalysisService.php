<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\WasteFinding;
use Illuminate\Support\Collection;

class WastedSpendAnalysisService
{
    public const VERSION = 'v1.0';

    public const MODULE_KEY = 'wasted_spend';

    public function analyze(AnalysisJob $job, array $technical, array $readiness): array
    {
        $findings = [];

        foreach ($technical['checks'] ?? [] as $check) {
            $finding = $this->fromTechnicalCheck($check);

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        foreach ($readiness['criteria'] ?? [] as $criterion) {
            $finding = $this->fromCriterion($criterion);

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        if (($job->crawledPages()->count()) > 0 && $findings === []) {
            $findings[] = [
                'finding_key' => 'no_obvious_leakage',
                'severity' => WasteFinding::SEVERITY_LOW,
                'title' => 'No major conversion leakage detected in this crawl',
                'evidence' => ['All audited checks passed within thresholds for this URL-only crawl.'],
                'potential_impact' => 'Outside a lab environment, residual UX leakage can still exist; integrations (analytics, ads) are required to measure real waste.',
                'correction' => 'Connect analytics and advertising integrations when available to quantify waste in money.',
            ];
        }

        return [
            'module_key' => self::MODULE_KEY,
            'version' => self::VERSION,
            'findings' => $findings,
            'disclaimer' => 'These findings are website conversion-leakage indicators only. Actual wasted advertising spend cannot be identified without advertising cost and conversion data (integrations, future phase).',
        ];
    }

    private function fromTechnicalCheck(array $check): ?array
    {
        if (! in_array($check['status'], ['fail', 'warn'], true)) {
            return null;
        }

        $map = [
            'https' => [WasteFinding::SEVERITY_HIGH, 'Non-HTTPS landing pages', 'Visitors hit browser warnings and platforms disapprove or discount non-secure landing pages.', 'Serve the website over HTTPS with a valid certificate.'],
            'status_codes' => [WasteFinding::SEVERITY_HIGH, 'Broken or error pages reachable', 'Paid clicks landing on errors convert at zero and inflate bounce signals.', 'Fix the failing URLs listed in the evidence before sending paid traffic.'],
            'titles' => [WasteFinding::SEVERITY_MEDIUM, 'Missing or duplicated page titles', 'Weak relevance signals hurt quality scores on search ads.', 'Give every page a unique descriptive title.'],
            'meta_descriptions' => [WasteFinding::SEVERITY_MEDIUM, 'Missing meta descriptions', 'Without controlled snippets, ads and search results may promise something the page does not deliver.', 'Write meta descriptions matching each ad variant.'],
            'mobile_viewport' => [WasteFinding::SEVERITY_HIGH, 'Pages not mobile-ready', 'Most paid traffic is mobile; pages without a viewport tag render unreadably and leak visitors.', 'Add the viewport meta tag to all templates.'],
            'load_time' => [WasteFinding::SEVERITY_MEDIUM, 'Slow observed load times', 'Each extra second of load time measurably increases abandonment from paid clicks.', 'Compress and defer assets; measure with Lighthouse after fixes.'],
            'content' => [WasteFinding::SEVERITY_MEDIUM, 'Very thin entry-page content', 'Visitors cannot evaluate the offer before leaving; low content targets hard to sell from.', 'Expand homepage copy with specific benefits and proof.'],
            'crawl_errors' => [WasteFinding::SEVERITY_MEDIUM, 'Crawl errors detected', 'Errors the crawler hit are errors visitors and ad reviewers can hit too.', 'Fix the underlying URLs listed on the website page.'],
        ];

        $meta = $map[$check['key']] ?? null;

        if ($meta === null) {
            return null;
        }

        [$severity, $impactLabel, $impact, $correction] = $meta;
        $severity = $check['status'] === 'warn' && $severity === WasteFinding::SEVERITY_HIGH ? WasteFinding::SEVERITY_MEDIUM : $severity;

        return [
            'finding_key' => 'technical_'.$check['key'],
            'severity' => $severity,
            'title' => $check['label'].' ('.$check['status'].(isset($check['value']) ? ': '.$check['value'] : '').')',
            'evidence' => $check['evidence'],
            'potential_impact' => $impact,
            'correction' => $correction,
        ];
    }

    private function fromCriterion(array $criterion): ?array
    {
        if ($criterion['score'] === null || $criterion['score'] >= 50) {
            return null;
        }

        $map = [
            'cta_effectiveness' => [
                WasteFinding::SEVERITY_HIGH,
                'Broken or missing calls to action',
                'Traffic arrives with no visible next step; clicks that would have converted leak away.',
                'Add specific action-first CTAs (starting on the entry page) and verify they scroll/target correctly.',
            ],
            'lead_capture_readiness' => [
                WasteFinding::SEVERITY_HIGH,
                'Missing lead capture path',
                'Interested visitors have no way to convert; every click is effectively first-touch churn.',
                'Add a short lead form and at least two contact channels to key pages.',
            ],
            'offer_clarity' => [
                WasteFinding::SEVERITY_MEDIUM,
                'Unclear offer',
                'Visitors cannot tell what they get or at what price, so they leave to compare elsewhere.',
                'State the offer, what is included, and the next step on the pages ads point to.',
            ],
            'trust_signals' => [
                WasteFinding::SEVERITY_MEDIUM,
                'Weak trust signals',
                'Low credibility cues depress conversion, especially for new brands on cold paid traffic.',
                'Surface testimonials, guarantees and privacy/terms links near CTAs.',
            ],
            'mobile_usability' => [
                WasteFinding::SEVERITY_HIGH,
                'Poor mobile experience signals',
                'Mobile share of paid clicks is typically dominant; usability gaps waste most of the spend.',
                'Fix viewport and template issues and verify on real devices.',
            ],
        ];

        $meta = $map[$criterion['key']] ?? null;

        if ($meta === null) {
            return null;
        }

        [$severity, $title, $impact, $correction] = $meta;

        return [
            'finding_key' => 'criterion_'.$criterion['key'],
            'severity' => $criterion['score'] < 30 ? WasteFinding::SEVERITY_HIGH : $severity,
            'title' => $title.': criterion score '.$criterion['score'].'/100',
            'evidence' => $criterion['evidence'],
            'potential_impact' => $impact,
            'correction' => $correction,
        ];
    }
}
