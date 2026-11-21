<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\CampaignInsight;
use App\Models\CrawledPage;
use Illuminate\Support\Collection;

class CampaignReadinessService
{
    public const VERSION = 'v1.0';

    public const MODULE_KEY = 'campaign_readiness';

    private const OFFER_PATTERNS = [
        'price', 'pricing', 'plan', 'plans', 'free', 'trial', 'offer', 'discount', 'quote', 'demo', 'book',
        'buy', 'cart', 'checkout', 'subscription', 'starting at', 'per month', '/mo', '$', '%',
    ];

    public function evaluate(AnalysisJob $job): array
    {
        $pages = $job->crawledPages()->orderBy('depth')->orderBy('id')->get();
        $homepage = $pages->firstWhere('depth', 0) ?? $pages->first();

        $criteria = [
            'value_proposition' => $this->valueProposition($pages, $homepage),
            'cta_effectiveness' => $this->ctaEffectiveness($pages, $homepage),
            'landing_page_relevance' => $this->landingPageRelevance($pages, $homepage),
            'offer_clarity' => $this->offerClarity($pages),
            'lead_capture_readiness' => $this->leadCaptureReadiness($pages, $homepage),
            'mobile_usability' => $this->mobileUsability($pages),
            'trust_signals' => $this->trustSignals($pages),
        ];

        $score = $this->weightedScore($criteria);
        $unknownCount = count(array_filter($criteria, fn ($c) => $c['score'] === null));

        $confidence = $unknownCount === 0 ? 'high' : ($unknownCount <= 1 ? 'medium' : 'low');

        return [
            'module_key' => self::MODULE_KEY,
            'score' => $score,
            'version' => self::VERSION,
            'methodology' => 'weighted heuristic (v1.0); URL-only evidence; not a validated ML prediction',
            'confidence' => $score === null ? 'low' : $confidence,
            'criteria' => array_values($criteria),
            'summary' => $score === null
                ? 'No crawl evidence available, so no score is calculated.'
                : 'Heuristic Campaign Readiness Score derived from '.(count($criteria) - $unknownCount).' of 7 criteria with crawl evidence.',
        ];
    }

    public function insights(array $result): array
    {
        $insights = [];

        foreach ($result['criteria'] ?? [] as $criterion) {
            if ($criterion['score'] === null || $criterion['score'] >= 70) {
                continue;
            }

            $insights[] = [
                'module_key' => self::MODULE_KEY,
                'priority' => $this->priority($criterion['score'], $criterion['weight']),
                'title' => $this->label($criterion['key']).': score '.$criterion['score'].'/100',
                'evidence' => $criterion['evidence'],
                'recommendation' => $criterion['recommendation'],
                'effort' => $this->effort($criterion['key']),
                'direction' => 'improvement',
                'confidence' => $criterion['confidence'],
            ];
        }

        return $insights;
    }

    public function technicalInsights(array $checks): array
    {
        return collect($checks)
            ->filter(fn ($check) => in_array($check['status'], ['fail', 'warn'], true) && $check['recommendation'] !== null)
            ->map(fn ($check) => [
                'module_key' => 'technical_audit',
                'priority' => $check['status'] === 'fail' ? CampaignInsight::PRIORITY_HIGH : CampaignInsight::PRIORITY_MEDIUM,
                'title' => $check['label'].': '.$check['status'].(isset($check['value']) ? ' ('.$check['value'].')' : ''),
                'evidence' => $check['evidence'],
                'recommendation' => $check['recommendation'],
                'effort' => $check['key'] === 'https' ? 'low' : 'medium',
                'direction' => 'improvement',
                'confidence' => 'high',
            ])
            ->values()
            ->all();
    }

    private function priority(int $score, int $weight): string
    {
        if ($score < 40) {
            return $weight >= 15 ? CampaignInsight::PRIORITY_HIGH : CampaignInsight::PRIORITY_MEDIUM;
        }

        if ($score < 70) {
            return $weight >= 10 ? CampaignInsight::PRIORITY_MEDIUM : CampaignInsight::PRIORITY_LOW;
        }

        return CampaignInsight::PRIORITY_LOW;
    }

    private function effort(string $key): string
    {
        return match ($key) {
            'cta_effectiveness', 'offer_clarity' => 'low',
            'value_proposition', 'trust_signals' => 'medium',
            'landing_page_relevance', 'lead_capture_readiness', 'mobile_usability' => 'medium',
            default => 'medium',
        };
    }

    private function weightedScore(array $criteria): ?int
    {
        $known = array_filter($criteria, fn ($c) => $c['score'] !== null);

        if ($known === []) {
            return null;
        }

        $totalWeight = array_sum(array_map(fn ($c) => $c['weight'], $known));

        return (int) round(array_sum(array_map(fn ($c) => $c['score'] * $c['weight'], $known)) / $totalWeight);
    }

    private function valueProposition(Collection $pages, ?CrawledPage $homepage): array
    {
        return $this->criterion(
            'value_proposition',
            20,
            $pages,
            function () use ($homepage): array {
                if ($homepage === null) {
                    return $this->unknown('The crawl did not capture the entry page, so value-proposition evidence is missing.');
                }

                $h1 = collect($homepage->headings['h1'] ?? [])->first();
                $score = 40;
                $evidence = [];

                if (filled($h1)) {
                    $score += 25;
                    $evidence[] = 'Homepage H1: "'.mb_substr((string) $h1, 0, 120).'"';

                    $h1Length = mb_strlen(trim((string) $h1));
                    $score += ($h1Length >= 10 && $h1Length <= 120) ? 5 : 0;
                } else {
                    $score -= 25;
                    $evidence[] = 'No H1 heading found on the homepage.';
                }

                $description = $homepage->meta['description'] ?? null;

                if (filled($description)) {
                    $score += 15;
                    $evidence[] = 'Homepage meta description present ('.mb_strlen((string) $description).' chars): "'.mb_substr((string) $description, 0, 120).'"';
                } else {
                    $evidence[] = 'No meta description on the homepage.';
                }

                if (($homepage->text_length ?? 0) >= 300) {
                    $score += 15;
                    $evidence[] = 'Homepage text volume: '.number_format((int) $homepage->text_length).' chars.';
                } else {
                    $evidence[] = 'Homepage text volume is thin ('.number_format((int) ($homepage->text_length ?? 0)).' chars).';
                }

                return [
                    'score' => max(5, min(95, $score)),
                    'evidence' => $evidence,
                ];
            },
            'The H1, meta description, and copy volume are the URL-visible proxies for how clearly a site communicates what it offers.',
            'State a specific, customer-facing value proposition in the H1 and meta description of the landing page.',
        );
    }

    private function ctaEffectiveness(Collection $pages, ?CrawledPage $homepage): array
    {
        return $this->criterion(
            'cta_effectiveness',
            20,
            $pages,
            function () use ($pages, $homepage): array {
                $labels = collect($pages)->flatMap(fn (CrawledPage $page) => $this->featureEvidence($page, 'cta_buttons'))
                    ->map(fn ($label) => mb_strtolower(trim((string) $label)))
                    ->filter()->unique()->values();
                $homepageLabels = $homepage ? $this->featureEvidence($homepage, 'cta_buttons') : collect();
                $hasSignup = collect($pages)->contains(fn (CrawledPage $page) => $this->featurePresent($page, 'signup_forms'));

                $evidence = $labels->take(5)->map(fn ($label) => 'CTA: "'.$label.'"')->values()->all();

                if ($labels->isEmpty()) {
                    return [
                        'score' => 15,
                        'evidence' => ['No CTA buttons or CTA links were detected on any crawled page.'],
                    ];
                }

                $score = match (true) {
                    $labels->count() === 1 => 40,
                    $labels->count() <= 3 => 70,
                    default => 85,
                };

                if ($homepageLabels->isEmpty()) {
                    $evidence[] = 'No CTA detected on the homepage/entry page.';
                    $score = min($score, 45);
                }

                if ($hasSignup) {
                    $score = min(95, $score + 10);
                    $evidence[] = 'A short signup form was detected.';
                }

                return [
                    'score' => min(95, $score),
                    'evidence' => $evidence,
                ];
            },
            'CTA link text is observable from the crawl; visual position above the fold is not, so this stays an approximation.',
            'Add a specific, action-first CTA on the entry page (for example "Get your free audit") and repeat it on key landing pages.',
        );
    }

    private function landingPageRelevance(Collection $pages, ?CrawledPage $homepage): array
    {
        return $this->criterion(
            'landing_page_relevance',
            15,
            $pages,
            function () use ($pages, $homepage): array {
                $pageCount = $pages->count();
                $score = match (true) {
                    $pageCount >= 5 => 75,
                    $pageCount >= 3 => 55,
                    default => 30,
                };

                $evidence = [$pageCount.' same-origin pages crawled within the 20-page cap.'];

                if ($homepage && ($homepage->text_length ?? 0) >= 200) {
                    $score += 15;
                    $evidence[] = 'Entry page text volume: '.number_format((int) $homepage->text_length).' chars.';
                }

                $described = $pages->filter(fn (CrawledPage $page) => filled($page->meta['description'] ?? null))->count();

                if ($pageCount > 0 && $described / $pageCount >= 0.5) {
                    $score += 10;
                    $evidence[] = $described.'/'.$pageCount.' pages carry a meta description.';
                }

                return [
                    'score' => min(95, $score),
                    'evidence' => $evidence,
                ];
            },
            'The crawl measures content breadth and metadata consistency, not subjective landing-page quality.',
            'Build or complete dedicated landing pages per offer, each with its own title, meta description, and tailored copy.',
        );
    }

    private function offerClarity(Collection $pages): array
    {
        return $this->criterion(
            'offer_clarity',
            15,
            $pages,
            function () use ($pages): array {
                $hits = collect();

                foreach ($pages as $page) {
                    $text = collect([
                        collect($page->headings ?? [])->flatten()->all(),
                        [(string) ($page->title ?? '')],
                        [(string) ($page->meta['description'] ?? '')],
                    ])->flatten()->implode(' ');

                    foreach (self::OFFER_PATTERNS as $pattern) {
                        if (stripos($text, $pattern) !== false) {
                            $hits->push($pattern);
                        }
                    }
                }

                $hits = $hits->unique()->values();

                if ($hits->isEmpty()) {
                    return [
                        'score' => 25,
                        'evidence' => ['No pricing or offer wording found in headings, titles, or meta descriptions of crawled pages.'],
                    ];
                }

                $score = match (true) {
                    $hits->count() <= 2 => 50,
                    $hits->count() <= 5 => 70,
                    default => 85,
                };

                return [
                    'score' => min(90, $score),
                    'evidence' => $hits->take(6)->map(fn ($hit) => 'Offer keyword found: "'.$hit.'"')->values()->all(),
                ];
            },
            'Offer words are detected in observable metadata and headings only; body copy beyond headings is not stored.',
            'Make the offer explicit where ads land: pricing or plan summary, what is included, and the next step to take it.',
        );
    }

    private function leadCaptureReadiness(Collection $pages, ?CrawledPage $homepage): array
    {
        return $this->criterion(
            'lead_capture_readiness',
            15,
            $pages,
            function () use ($pages): array {
                $score = 0;
                $evidence = [];

                $rules = [
                    ['lead_forms', 40, 'Lead/contact form detected'],
                    ['signup_forms', 15, 'Short signup form detected'],
                    ['email_links', 10, 'mailto: contact link detected'],
                    ['phone_links', 10, 'tel: phone link detected'],
                    ['whatsapp_links', 10, 'WhatsApp click-to-chat link detected'],
                    ['booking_links', 15, 'Booking / calendar link detected'],
                ];

                foreach ($rules as [$key, $points, $label]) {
                    $present = collect($pages)->contains(fn (CrawledPage $page) => $this->featurePresent($page, $key));

                    if ($present) {
                        $score += $points;
                        $sample = collect($pages)->flatMap(fn (CrawledPage $page) => $this->featureEvidence($page, $key))->filter()->first();
                        $evidence[] = $label.($sample !== null ? ' (e.g. '.mb_substr((string) $sample, 0, 80).')' : '');
                    }
                }

                return [
                    'score' => $score === 0 ? 10 : min(95, $score),
                    'evidence' => $evidence === [] ? ['No forms or contact methods detected on any crawled page.'] : $evidence,
                ];
            },
            'Form and contact-link presence is directly observed; form usability and actual conversion behaviour are not measurable from a URL.',
            'Give every key landing page one short lead form and at least two direct contact channels (email and phone or WhatsApp).',
        );
    }

    private function mobileUsability(Collection $pages): array
    {
        return $this->criterion(
            'mobile_usability',
            10,
            $pages,
            function () use ($pages): array {
                $total = $pages->count();
                $responsive = $pages->filter(fn (CrawledPage $page) => $this->featurePresent($page, 'mobile_viewport_meta'))->count();
                $share = $total > 0 ? $responsive / $total : 0;

                return [
                    'score' => match (true) {
                        $share >= 0.5 => 90,
                        $share > 0 => 60,
                        default => 15,
                    },
                    'evidence' => [$responsive.'/'.$total.' crawled pages declare a mobile viewport meta tag.'],
                ];
            },
            'The viewport tag is a minimal mobile signal; rendering quality exceeds what a URL-only crawl can measure.',
            'Ensure every template includes a mobile viewport meta tag and test key pages on a real phone.',
        );
    }

    private function trustSignals(Collection $pages): array
    {
        return $this->criterion(
            'trust_signals',
            5,
            $pages,
            function () use ($pages): array {
                $count = collect($pages)->sum(fn (CrawledPage $page) => $this->featureCount($page, 'trust_signals'));
                $samples = collect($pages)->flatMap(fn (CrawledPage $page) => $this->featureEvidence($page, 'trust_signals'))->filter()->unique()->take(6)->values();

                if ($count === 0) {
                    return [
                        'score' => 20,
                        'evidence' => ['No trust-signal wording (testimonials, guarantees, privacy policy, certifications and similar) found in crawled content.'],
                    ];
                }

                $score = match (true) {
                    $count >= 5 => 85,
                    default => 60,
                };

                return [
                    'score' => $score,
                    'evidence' => $samples->map(fn ($sample) => 'Trust signal: "'.$sample.'"')->values()->all(),
                ];
            },
            'Trust wording is observable; external review volume or reputation is not measurable from a URL.',
            'Surface proof near CTAs: testimonials, guarantees, recognizable client logos, and linked privacy/terms pages.',
        );
    }

    private function label(string $key): string
    {
        return str_replace('Cta', 'CTA', ucfirst(str_replace('_', ' ', $key)));
    }

    private function criterion(string $key, int $weight, Collection $pages, callable $compute, string $explanation, string $recommendation): array
    {
        $base = [
            'key' => $key,
            'label' => $this->label($key),
            'weight' => $weight,
            'explanation' => $explanation,
            'recommendation' => $recommendation,
        ];

        if ($pages->isEmpty()) {
            return [...$base, 'score' => null, 'evidence' => [], 'confidence' => 'low'];
        }

        $result = $compute();

        return [
            ...$base,
            'score' => $result['score'],
            'evidence' => $result['evidence'],
            'confidence' => 'medium',
        ];
    }

    private function unknown(string $explanation): array
    {
        return [
            'score' => null,
            'evidence' => [],
            'explanation' => $explanation,
        ];
    }

    private function featuresOf(CrawledPage $page): Collection
    {
        return collect($page->features ?? [])->keyBy('feature_key');
    }

    private function featurePresent(CrawledPage $page, string $key): bool
    {
        return (bool) ($this->featuresOf($page)->get($key)['present'] ?? false);
    }

    private function featureCount(CrawledPage $page, string $key): int
    {
        return (int) ($this->featuresOf($page)->get($key)['count'] ?? 0);
    }

    private function featureEvidence(CrawledPage $page, string $key): Collection
    {
        return collect($this->featuresOf($page)->get($key)['evidence'] ?? []);
    }
}
