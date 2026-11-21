<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use Illuminate\Support\Collection;

class ConversionOpportunityService
{
    public const VERSION = 'v1.0';

    public const MODULE_KEY = 'conversion_opportunity';

    private const MAX_RAW = 25.0;

    public function analyze(AnalysisJob $job): array
    {
        $pages = $job->crawledPages()->orderBy('depth')->orderBy('id')->get();
        $homepage = $pages->firstWhere('depth', 0) ?? $pages->first();

        $opportunities = [
            'cta_placement' => $this->ctaPlacement($pages, $homepage),
            'form_complexity' => $this->formComplexity($pages),
            'content_clarity' => $this->contentClarity($pages, $homepage),
            'value_proposition' => $this->valueProposition($pages, $homepage),
            'navigation' => $this->navigation($pages),
            'landing_relevance' => $this->landingRelevance($pages, $homepage),
            'mobile_usability' => $this->mobileUsability($pages),
            'trust_signals' => $this->trustSignals($pages),
            'contact_accessibility' => $this->contactAccessibility($pages),
            'page_performance' => $this->pagePerformance($pages),
        ];

        $known = array_filter($opportunities, fn ($item) => $item['score'] !== null);

        $aggregate = $known === []
            ? null
            : (int) round(100 * array_sum(array_column($known, 'raw')) / (self::MAX_RAW * count($known)));

        return [
            'module_key' => self::MODULE_KEY,
            'score' => $aggregate,
            'version' => self::VERSION,
            'label' => 'Heuristic Conversion Opportunity Score',
            'methodology' => 'score = severity x impact x confidence per opportunity, normalized to 0-100; heuristic, not a validated conversion probability',
            'opportunities' => array_values($opportunities),
            'high' => array_values(array_filter($opportunities, fn ($o) => $o['priority'] === 'high')),
            'medium' => array_values(array_filter($opportunities, fn ($o) => $o['priority'] === 'medium')),
            'low' => array_values(array_filter($opportunities, fn ($o) => $o['priority'] === 'low')),
        ];
    }

    private function opportunity(string $key, string $title, ?int $severity, ?int $impact, ?int $confidence, array $evidence, string $recommendation, string $effort): array
    {
        $priority = 'low';

        if ($severity !== null && $impact !== null && $confidence !== null) {
            $raw = $severity * $impact * ($confidence / 100);
            $score = (int) round(100 * $raw / self::MAX_RAW);
            $priority = match (true) {
                $score >= 50 => 'high',
                $score >= 25 => 'medium',
                default => 'low',
            };
        }

        return [
            'key' => $key,
            'title' => $title,
            'priority' => $priority,
            'severity' => $severity,
            'impact' => $impact,
            'confidence' => $confidence,
            'score' => ($severity !== null && $impact !== null && $confidence !== null)
                ? (int) round(100 * ($severity * $impact * ($confidence / 100)) / self::MAX_RAW)
                : null,
            'evidence' => $evidence,
            'recommendation' => $recommendation,
            'effort' => $effort,
            'direction' => 'improvement',
            'raw' => ($severity !== null && $impact !== null && $confidence !== null)
                ? $severity * $impact * ($confidence / 100)
                : 0.0,
        ];
    }

    private function ctaPlacement(Collection $pages, ?CrawledPage $homepage): array
    {
        $homepageCtas = $homepage ? $this->feature($homepage, 'cta_buttons') : null;
        $evidence = $homepageCtas === null
            ? []
            : collect($this->featureEvidence($homepage, 'cta_buttons'))->take(3)->values()->all();

        if ($pages->isEmpty()) {
            return $this->opportunity('cta_placement', 'CTA placement', null, null, null, [], 'Add a clear, action-first call to action on the entry page.', 'low');
        }

        if ($homepageCtas === true) {
            return $this->opportunity('cta_placement', 'CTA placement', 2, 4, 60, ['CTA links detected on the entry page.', ...$evidence], 'Test stronger CTA wording and repetition on longer pages.', 'low');
        }

        if ($homepageCtas === false) {
            return $this->opportunity('cta_placement', 'CTA placement', 5, 5, 90, ['No call-to-action link or button was detected on the entry page.'], 'Add a clear, action-first call to action above the main content of the entry page.', 'low');
        }

        return $this->opportunity('cta_placement', 'CTA placement', null, null, null, [], 'Add a clear, action-first call to action on the entry page.', 'low');
    }

    private function formComplexity(Collection $pages): array
    {
        if ($pages->isEmpty()) {
            return $this->opportunity('form_complexity', 'Form complexity', null, null, null, [], 'Keep lead forms short: only fields you actually use.', 'medium');
        }

        $formPages = $pages->filter(fn (CrawledPage $page) => $this->featurePresent($page, 'lead_forms') || $this->featurePresent($page, 'signup_forms'));

        if ($formPages->isEmpty()) {
            return $this->opportunity('form_complexity', 'Form complexity', 5, 5, 85, ['No lead or signup form was found on any crawled page.'], 'Add a short lead form to the pages ads will point to.', 'medium');
        }

        $sample = collect($formPages->flatMap(fn (CrawledPage $page) => $this->featureEvidence($page, 'lead_forms')))->first();

        return [
            'key' => 'form_complexity',
            'title' => 'Form complexity',
            'priority' => 'low',
            'severity' => 2,
            'impact' => 3,
            'confidence' => 50,
            'score' => (int) round(100 * (2 * 3 * 0.5) / self::MAX_RAW),
            'evidence' => ['Forms detected on '.$formPages->count().' page(s).', $sample !== null ? 'Sample: '.$sample : ''],
            'recommendation' => 'Review form field counts; remove fields you do not use to reduce friction.',
            'effort' => 'low',
            'direction' => 'improvement',
            'raw' => 2 * 3 * 0.5,
        ];
    }

    private function contentClarity(Collection $pages, ?CrawledPage $homepage): array
    {
        $length = $homepage?->text_length;

        if ($length === null) {
            return $this->opportunity('content_clarity', 'Content clarity', null, null, null, [], 'Publish scannable copy that explains what you offer and for whom.', 'medium');
        }

        if ($length < 200) {
            return $this->opportunity('content_clarity', 'Content clarity', 4, 4, 80, ['Entry page text volume is only '.number_format((int) $length).' chars.'], 'Publish scannable copy that explains what you offer and for whom.', 'medium');
        }

        return $this->opportunity('content_clarity', 'Content clarity', 2, 3, 60, ['Entry page text volume: '.number_format((int) $length).' chars.'], 'Audit copy for clarity; lead with the outcome, not the product.', 'medium');
    }

    private function valueProposition(Collection $pages, ?CrawledPage $homepage): array
    {
        $h1 = collect($homepage?->headings['h1'] ?? [])->first();

        if ($h1 === null) {
            return $this->opportunity('value_proposition', 'Value proposition', 5, 5, 85, ['No H1 heading found on the entry page.'], 'State a specific value proposition in the entry-page H1 and meta description.', 'medium');
        }

        return $this->opportunity('value_proposition', 'Value proposition', 2, 4, 60, ['Entry page H1: "'.mb_substr((string) $h1, 0, 80).'"'], 'Test a more specific value-proposition H1 against the current wording.', 'medium');
    }

    private function navigation(Collection $pages): array
    {
        $withNav = $pages->filter(fn (CrawledPage $page) => $this->featurePresent($page, 'navigation'))->count();
        $total = $pages->count();
        $share = $total > 0 ? $withNav / max(1, $total) : 0;

        if ($share < 0.5) {
            return $this->opportunity('navigation', 'Navigation', 3, 4, 70, ["Only {$withNav}/{$total} crawled pages expose a navigation element."], 'Add consistent navigation so visitors can find offer and contact pages.', 'medium');
        }

        return $this->opportunity('navigation', 'Navigation', 1, 3, 70, ["Navigation detected on {$withNav}/{$total} pages."], 'Keep navigation consistent; make sure offer pages are reachable in 1-2 clicks.', 'low');
    }

    private function landingRelevance(Collection $pages, ?CrawledPage $homepage): array
    {
        $pageCount = $pages->count();

        if ($pageCount <= 2) {
            return $this->opportunity('landing_relevance', 'Landing page relevance', 4, 4, 75, ["Only {$pageCount} same-origin page(s) were available to crawl."], 'Publish dedicated landing pages matching each offer and audience.', 'high');
        }

        return $this->opportunity('landing_relevance', 'Landing page relevance', 2, 4, 60, ["{$pageCount} same-origin pages crawled."], 'Match each ad to a dedicated landing page; avoid sending all traffic to the homepage.', 'medium');
    }

    private function mobileUsability(Collection $pages): array
    {
        $responsive = $pages->filter(fn (CrawledPage $page) => $this->featurePresent($page, 'mobile_viewport_meta'))->count();
        $total = $pages->count();
        $share = $total > 0 ? $responsive / max(1, $total) : 0;

        if ($share < 0.5) {
            return $this->opportunity('mobile_usability', 'Mobile usability', 5, 5, 90, ["Only {$responsive}/{$total} pages declare a mobile viewport tag."], 'Add the viewport meta tag to all templates and verify mobile rendering.', 'medium');
        }

        return $this->opportunity('mobile_usability', 'Mobile usability', 1, 4, 80, ["Viewport tag present on {$responsive}/{$total} pages."], 'Spot-check key pages on a real phone; viewport is only a minimum signal.', 'low');
    }

    private function trustSignals(Collection $pages): array
    {
        $count = $pages->sum(fn (CrawledPage $page) => $this->featureCount($page, 'trust_signals'));

        if ($count === 0) {
            return $this->opportunity('trust_signals', 'Trust signals', 4, 3, 80, ['No trust-signal wording found in crawled content.'], 'Add testimonials, guarantees, or recognizable client logos near the CTA.', 'medium');
        }

        return $this->opportunity('trust_signals', 'Trust signals', 2, 3, 70, ["{$count} trust-signal hits across crawled pages."], 'Move the strongest proof next to the CTA on key landing pages.', 'low');
    }

    private function contactAccessibility(Collection $pages): array
    {
        $keys = ['email_links', 'phone_links', 'whatsapp_links', 'booking_links'];
        $found = [];

        foreach ($keys as $key) {
            if ($pages->contains(fn (CrawledPage $page) => $this->featurePresent($page, $key))) {
                $found[] = $key;
            }
        }

        if (count($found) === 0) {
            return $this->opportunity('contact_accessibility', 'Contact accessibility', 4, 4, 85, ['No direct contact link (email, phone, WhatsApp, booking) was found in crawled pages.'], 'Add at least two direct contact channels to every key page.', 'low');
        }

        if (count($found) === 1) {
            return $this->opportunity('contact_accessibility', 'Contact accessibility', 3, 3, 70, ['Only one direct contact channel detected: '.reset($found).'.'], 'Add a second direct contact channel (for example phone alongside email).', 'low');
        }

        return $this->opportunity('contact_accessibility', 'Contact accessibility', 1, 3, 70, ['Contact channels detected: '.implode(', ', $found).'.'], 'Keep contact methods visible without scrolling on mobile.', 'low');
    }

    private function pagePerformance(Collection $pages): array
    {
        $timings = $pages->filter(fn (CrawledPage $page) => $page->load_time_ms !== null);

        if ($timings->isEmpty()) {
            return $this->opportunity('page_performance', 'Page performance', null, null, null, [], 'Measure landing-page load time (Lighthouse) and keep it under ~2.5s.', 'medium');
        }

        $average = $timings->avg('load_time_ms');

        if ($average > 3000) {
            return $this->opportunity('page_performance', 'Page performance', 5, 4, 80, ['Average observed load time '.round($average).' ms.'], 'Compress images, defer scripts, and cache assets to get under ~2.5s.', 'medium');
        }

        if ($average > 1500) {
            return $this->opportunity('page_performance', 'Page performance', 3, 4, 70, ['Average observed load time '.round($average).' ms.'], 'Trim render-blocking assets to push load time toward ~1.5s.', 'medium');
        }

        return $this->opportunity('page_performance', 'Page performance', 1, 3, 70, ['Average observed load time '.round($average).' ms.'], 'Maintain current asset discipline; re-check after redesigns.', 'low');
    }

    private function feature(CrawledPage $page, string $key): ?bool
    {
        $features = collect($page->features ?? [])->keyBy('feature_key');

        return $features->has($key) ? (bool) $features->get($key)['present'] : null;
    }

    private function featurePresent(CrawledPage $page, string $key): bool
    {
        return (bool) ($this->feature($page, $key) ?? false);
    }

    private function featureCount(CrawledPage $page, string $key): int
    {
        return (int) (collect($page->features ?? [])->keyBy('feature_key')->get($key)['count'] ?? 0);
    }

    private function featureEvidence(CrawledPage $page, string $key): Collection
    {
        return collect(collect($page->features ?? [])->keyBy('feature_key')->get($key)['evidence'] ?? []);
    }
}
