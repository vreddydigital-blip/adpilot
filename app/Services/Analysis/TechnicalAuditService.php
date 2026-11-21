<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;

class TechnicalAuditService
{
    public const VERSION = 'v1.0';

    public function audit(AnalysisJob $job): array
    {
        $pages = $job->crawledPages()->orderBy('depth')->orderBy('id')->get();
        $errors = $job->crawlErrors()->count();

        if ($pages->isEmpty()) {
            return ['score' => null, 'checks' => [], 'confidence' => 'low'];
        }

        $checks = [
            $this->checkHttps($pages),
            $this->checkStatusCodes($pages),
            $this->checkTitles($pages),
            $this->checkMetaDescriptions($pages),
            $this->checkMobileViewport($pages),
            $this->checkLoadTime($pages),
            $this->checkHomepageContent($pages->firstWhere('depth', 0) ?? $pages->first()),
            $this->checkCrawlErrors($errors, $pages->count()),
        ];

        $known = array_values(array_filter($checks, fn ($check) => $check['status'] !== 'unknown'));

        if ($known === []) {
            return ['score' => null, 'checks' => $checks, 'confidence' => 'low'];
        }

        $value = array_sum(array_map(
            fn ($check) => match ($check['status']) {
                'pass' => 1.0,
                'warn' => 0.5,
                default => 0.0,
            },
            $known,
        ));

        return [
            'score' => (int) round(100 * $value / count($known)),
            'checks' => $checks,
            'confidence' => count($known) === count($checks) ? 'high' : 'medium',
        ];
    }

    private function checkHttps($pages): array
    {
        $homepage = $pages->firstWhere('depth', 0) ?? $pages->first();
        $scheme = strtolower((string) (parse_url((string) ($homepage->final_url ?: $homepage->url), PHP_URL_SCHEME) ?: ''));
        $statusCode = $homepage->http_status;
        $served = $statusCode !== null && $statusCode >= 200 && $statusCode < 400;

        return [
            'key' => 'https',
            'label' => 'HTTPS usage on entry page',
            'status' => $scheme === 'https' ? ($served ? 'pass' : 'unknown') : 'fail',
            'value' => $scheme === '' ? 'unknown' : $scheme,
            'evidence' => ['Entry page URL: '.($homepage->final_url ?: $homepage->url)],
            'explanation' => 'Ads pointing at a non-HTTPS landing page lose visitors to browser warnings and ad-platform disapproval.',
            'recommendation' => $scheme === 'https' ? null : 'Serve the website over HTTPS with a valid certificate.',
        ];
    }

    private function checkStatusCodes($pages): array
    {
        $total = $pages->count();
        $ok = $pages->filter(fn (CrawledPage $page) => $page->http_status !== null && $page->http_status >= 200 && $page->http_status < 300)->count();
        $share = $total > 0 ? ((float) $ok) / (float) $total : 0.0;

        return [
            'key' => 'status_codes',
            'label' => 'Successful page responses',
            'status' => $share === 1.0 ? 'pass' : ($share >= 0.7 ? 'warn' : 'fail'),
            'value' => $ok.'/'.$total.' pages returned 2xx',
            'evidence' => $pages->filter(fn (CrawledPage $page) => $page->http_status === null || $page->http_status >= 300)
                ->map(fn (CrawledPage $page) => ($page->http_status ?? 'no-status').' '.$page->url)->take(5)->values()->all(),
            'explanation' => 'Broken pages directly block paid traffic from converting.',
            'recommendation' => $share === 1.0 ? null : 'Fix the non-2xx pages listed in the evidence.',
        ];
    }

    private function checkTitles($pages): array
    {
        $total = $pages->count();
        $withTitle = $pages->filter(fn (CrawledPage $page) => filled($page->title))->count();
        $share = $total > 0 ? $withTitle / (float) $total : 0.0;
        $duplicateTitles = $pages->filter(fn (CrawledPage $page) => filled($page->title))
            ->groupBy(fn (CrawledPage $page) => mb_strtolower(trim((string) $page->title)))
            ->filter(fn ($group) => $group->count() > 1);

        return [
            'key' => 'titles',
            'label' => 'Page titles present and unique',
            'status' => $share < 1.0 ? 'fail' : ($duplicateTitles->isNotEmpty() ? 'warn' : 'pass'),
            'value' => $withTitle.'/'.$total.' pages have titles',
            'evidence' => collect([
                $share < 1.0 ? 'Pages without a title: '.$pages->filter(fn (CrawledPage $page) => blank($page->title))->map(fn (CrawledPage $page) => $page->url)->implode(', ') : null,
                $duplicateTitles->isNotEmpty() ? 'Duplicated titles: '.implode(', ', array_keys($duplicateTitles->all())) : null,
            ])->filter()->values()->all(),
            'explanation' => 'Titles are the strongest relevance signal for search ads and landing-page quality.',
            'recommendation' => $share === 1.0 && $duplicateTitles->isEmpty() ? null : 'Give every page a unique, descriptive title tag.',
        ];
    }

    private function checkMetaDescriptions($pages): array
    {
        $total = $pages->count();
        $withDescription = $pages->filter(fn (CrawledPage $page) => filled(($page->meta['description'] ?? null)))->count();
        $share = $total > 0 ? $withDescription / (float) $total : 0.0;

        return [
            'key' => 'meta_descriptions',
            'label' => 'Meta description coverage',
            'status' => $share >= 0.7 ? 'pass' : ($share >= 0.3 ? 'warn' : 'fail'),
            'value' => $withDescription.'/'.$total.' pages',
            'evidence' => $pages->filter(fn (CrawledPage $page) => blank($page->meta['description'] ?? null))
                ->map(fn (CrawledPage $page) => $page->url)->take(5)->values()->all(),
            'explanation' => 'Meta descriptions are widely used as the ad/serp snippet source; missing ones reduce message control.',
            'recommendation' => $share < 1.0 ? 'Add a descriptive meta description to every important page.' : null,
        ];
    }

    private function checkMobileViewport($pages): array
    {
        $total = $pages->count();
        $responsive = $pages->filter(fn (CrawledPage $page) => $this->feature($page, 'mobile_viewport_meta') === true)->count();
        $share = $total > 0 ? $responsive / (float) $total : 0.0;

        return [
            'key' => 'mobile_viewport',
            'label' => 'Mobile viewport meta tag',
            'status' => $share === 1.0 ? 'pass' : ($share >= 0.5 ? 'warn' : 'fail'),
            'value' => $responsive.'/'.$total.' pages',
            'evidence' => $pages->filter(fn (CrawledPage $page) => $this->feature($page, 'mobile_viewport_meta') === false)
                ->map(fn (CrawledPage $page) => $page->url)->take(5)->values()->all(),
            'explanation' => 'A viewport meta tag is required for the page to render correctly on mobile ad traffic.',
            'recommendation' => $share < 1.0 ? 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> to all pages.' : null,
        ];
    }

    private function checkLoadTime($pages): array
    {
        $timings = $pages->filter(fn (CrawledPage $page) => $page->load_time_ms !== null);

        if ($timings->isEmpty()) {
            return [
                'key' => 'load_time',
                'label' => 'Observed load time',
                'status' => 'unknown',
                'value' => null,
                'evidence' => [],
                'explanation' => 'Load time was not reported by the crawler for any crawled page.',
                'recommendation' => null,
            ];
        }

        $average = $timings->avg('load_time_ms');

        return [
            'key' => 'load_time',
            'label' => 'Observed load time',
            'status' => $average <= 1500 ? 'pass' : ($average <= 3000 ? 'warn' : 'fail'),
            'value' => (int) round($average).' ms average over '.$timings->count().' pages',
            'evidence' => $timings->sortByDesc('load_time_ms')->take(3)
                ->map(fn (CrawledPage $page) => $page->load_time_ms.' ms '.$page->url)->values()->all(),
            'explanation' => 'Measured on the crawler runner, not a managed lab environment; used as a relative indicator only.',
            'recommendation' => $average <= 3000 ? null : 'Reduce landing-page load time; every extra second of latency costs paid conversions.',
        ];
    }

    private function checkHomepageContent(?CrawledPage $homepage): array
    {
        if ($homepage === null || $homepage->text_length === null) {
            return [
                'key' => 'content',
                'label' => 'Homepage content depth',
                'status' => 'unknown',
                'value' => null,
                'evidence' => [],
                'explanation' => 'The crawl did not capture text volume for the entry page.',
                'recommendation' => null,
            ];
        }

        $length = (int) $homepage->text_length;

        return [
            'key' => 'content',
            'label' => 'Homepage content depth',
            'status' => $length >= 500 ? 'pass' : ($length >= 200 ? 'warn' : 'fail'),
            'value' => number_format($length).' chars of extracted text',
            'evidence' => ['Homepage: '.($homepage->final_url ?: $homepage->url)],
            'explanation' => 'A very thin homepage gives visitors nothing to evaluate before the back button.',
            'recommendation' => $length >= 500 ? null : 'Expand homepage copy: communicate what you offer, for whom, and why trust you.',
        ];
    }

    private function checkCrawlErrors(int $errors, int $pages): array
    {
        return [
            'key' => 'crawl_errors',
            'label' => 'Crawl errors encountered',
            'status' => $errors === 0 ? 'pass' : ($errors <= 5 ? 'warn' : 'fail'),
            'value' => $errors.' errors across '.$pages.' pages',
            'evidence' => [],
            'explanation' => 'Crawl errors are direct evidence of accessibility problems visitors also hit.',
            'recommendation' => $errors === 0 ? null : 'Review the crawl errors listed on the website page and fix the underlying URLs.',
        ];
    }

    private function feature(CrawledPage $page, string $key): ?bool
    {
        $features = collect($page->features ?? [])->keyBy('feature_key');

        return $features->has($key) ? (bool) $features->get($key)['present'] : null;
    }
}
