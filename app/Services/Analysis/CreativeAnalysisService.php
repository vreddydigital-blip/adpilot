<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use Illuminate\Support\Collection;

class CreativeAnalysisService
{
    public const VERSION = 'v1.0';

    public const MODULE_KEY = 'creative_intelligence';

    private const GOOGLE_HEADLINE_MAX = 30;

    private const GOOGLE_DESCRIPTION_MAX = 90;

    private const META_HEADLINE_MAX = 40;

    private const META_PRIMARY_MAX = 125;

    public function generate(AnalysisJob $job): array
    {
        $pages = $job->crawledPages()->orderBy('depth')->orderBy('id')->get();
        $homepage = $pages->firstWhere('depth', 0) ?? $pages->first();

        if ($homepage === null) {
            return [];
        }

        $brand = $this->brand($homepage);
        $h1 = (string) (collect($homepage->headings['h1'] ?? [])->first() ?? '');
        $description = (string) ($homepage->meta['description'] ?? '');
        $title = (string) ($homepage->title ?? '');
        $ctas = $this->featureEvidence($homepage, 'cta_buttons');
        $offerWords = $this->offerWords($pages);

        $headlines = $this->headlines($brand, $h1, $title, $offerWords, $ctas);
        $descriptions = $this->descriptions($description, $brand, $h1, $title);
        $metaPrimary = $this->metaPrimary($description, $brand, $h1);
        $metaHeadlines = $this->metaHeadlines($brand, $h1, $title, $offerWords);
        $angles = $this->angles($offerWords, $h1);
        $match = $this->messageMatch($headlines, [$h1, $title, $description]);

        return [
            'google_headlines' => $headlines,
            'google_descriptions' => $descriptions,
            'meta_primary_text' => $metaPrimary,
            'meta_headlines' => $metaHeadlines,
            'angles' => $angles,
            'message_match' => $match,
            'source' => [
                'entry_page' => $homepage->final_url ?: $homepage->url,
                'h1' => $h1,
                'meta_description' => $description,
                'ctas' => $ctas->take(5)->values()->all(),
            ],
            'disclaimer' => 'Deterministic templates assembled from your website\'s own titles, headings, descriptions and CTA labels. No CTR or conversion predictions are made.',
        ];
    }

    private function headlines(string $brand, string $h1, string $title, Collection $offerWords, Collection $ctas): array
    {
        $candidates = [];

        foreach ([$h1, $title] as $source) {
            $text = trim($source);

            if ($text === '') {
                continue;
            }

            if ($text !== $brand) {
                $candidates[] = $text;
            }

            $firstWords = $this->firstWords($text, 6);

            if (mb_strlen($firstWords) >= 12) {
                $candidates[] = $firstWords;
            }
        }

        if ($brand !== '') {
            $candidates[] = $brand;
            $bestOffer = $offerWords->first();

            if ($bestOffer !== null && mb_strlen($brand.' - '.ucfirst($bestOffer)) <= self::GOOGLE_HEADLINE_MAX) {
                $candidates[] = $brand.' - '.ucfirst($bestOffer);
            }
        }

        $cta = $ctas->first();

        if ($cta !== null && mb_strlen((string) $cta) <= self::GOOGLE_HEADLINE_MAX) {
            $candidates[] = (string) $cta;
        }

        return collect($candidates)
            ->map(fn (string $headline) => [
                'text' => $headline,
                'char_length' => mb_strlen($headline),
            ])
            ->filter(fn (array $headline) => $headline['char_length'] >= 5 && $headline['char_length'] <= self::GOOGLE_HEADLINE_MAX)
            ->unique(fn (array $headline) => mb_strtolower($headline['text']))
            ->take(8)
            ->values()
            ->all();
    }

    private function descriptions(string $description, string $brand, string $h1, string $title): array
    {
        $candidates = [];

        if (trim($description) !== '') {
            $candidates[] = $description;
        }

        $fallback = trim($h1 !== '' ? $h1 : $title);

        if ($fallback !== '' && $brand !== '') {
            $candidates[] = $fallback.' Learn more at '.$brand.'.';
        }

        return collect($candidates)
            ->map(fn (string $text) => [
                'text' => $this->fit($text, self::GOOGLE_DESCRIPTION_MAX),
                'char_length' => mb_strlen($this->fit($text, self::GOOGLE_DESCRIPTION_MAX)),
            ])
            ->filter(fn (array $desc) => $desc['char_length'] >= 30)
            ->unique(fn (array $desc) => mb_strtolower($desc['text']))
            ->take(4)
            ->values()
            ->all();
    }

    private function metaPrimary(string $description, string $brand, string $h1): array
    {
        $candidates = [];

        if (trim($description) !== '') {
            $candidates[] = $description;
        }

        if ($h1 !== '') {
            $candidates[] = $h1.($brand !== '' ? ' - '.$brand : '');
        }

        return collect($candidates)
            ->map(fn (string $text) => [
                'text' => $this->fit($text, self::META_PRIMARY_MAX),
                'char_length' => mb_strlen($this->fit($text, self::META_PRIMARY_MAX)),
            ])
            ->filter(fn (array $text) => $text['char_length'] >= 20)
            ->unique(fn (array $text) => mb_strtolower($text['text']))
            ->take(3)
            ->values()
            ->all();
    }

    private function metaHeadlines(string $brand, string $h1, string $title, Collection $offerWords): array
    {
        $candidates = [$brand, $h1, $title, $brand.': '.($offerWords->first() ? ucfirst((string) $offerWords->first()) : 'Official site')];

        return collect($candidates)
            ->filter(fn (string $text) => trim($text) !== '')
            ->map(fn (string $text) => [
                'text' => $this->fit($text, self::META_HEADLINE_MAX),
                'char_length' => mb_strlen($this->fit($text, self::META_HEADLINE_MAX)),
            ])
            ->filter(fn (array $headline) => $headline['char_length'] >= 5)
            ->unique(fn (array $headline) => mb_strtolower($headline['text']))
            ->take(5)
            ->values()
            ->all();
    }

    private function angles(Collection $offerWords, string $h1): array
    {
        $angles = [];

        if ($offerWords->contains(fn ($word) => in_array($word, ['free', 'trial', 'demo', 'quote']))) {
            $angles[] = 'Risk-reversal angle: lead with the free/demo/quote hook visible in the site copy.';
        }

        if ($offerWords->contains(fn ($word) => in_array($word, ['price', 'pricing', 'plan', 'plans']))) {
            $angles[] = 'Transparency angle: ads that name pricing tiers tend to pre-qualify clicks.';
        }

        if ($offerWords->contains(fn ($word) => in_array($word, ['book', 'buy', 'cart', 'checkout']))) {
            $angles[] = 'Direct-response angle: point ads at the product/booking page, not the homepage.';
        }

        $angles[] = 'Problem-solution angle: describe the visitor problem in the ad headline, then match it on the landing page.';

        return $angles;
    }

    private function messageMatch(array $headlines, array $pageTexts): array
    {
        $pageTokens = collect($pageTexts)
            ->flatMap(fn (string $text) => $this->tokens($text))
            ->unique()
            ->values();

        if ($pageTokens->isEmpty() || $headlines === []) {
            return [
                'overlap_percent' => null,
                'verdict' => 'unknown',
                'note' => 'Not enough landing-page text was captured to compute a message match.',
            ];
        }

        $scores = collect($headlines)
            ->map(function (array $headline) use ($pageTokens) {
                $tokens = collect($this->tokens($headline['text']));
                $hits = $tokens->filter(fn (string $token) => $pageTokens->contains(fn (string $pageToken) => str_starts_with($pageToken, $token)));

                return $tokens->isNotEmpty() ? $hits->count() / $tokens->count() : 0.0;
            });

        $average = $scores->avg();

        return [
            'overlap_percent' => (int) round(100 * $average),
            'verdict' => $average >= 0.6 ? 'good' : ($average >= 0.3 ? 'partial' : 'low'),
            'note' => 'Share of generated-ad words that also appear in the landing page text. This is a text-overlap measure only - it does not predict CTR or conversion performance.',
        ];
    }

    private function tokens(string $text): array
    {
        return collect(preg_split('/[^a-z0-9]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->filter(fn (string $token) => mb_strlen($token) >= 3)
            ->all();
    }

    private function firstWords(string $text, int $words): string
    {
        $parts = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $subset = implode(' ', array_slice($parts, 0, $words));

        return $this->fit($subset, self::GOOGLE_HEADLINE_MAX);
    }

    private function fit(string $text, int $maxLength): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);

        $lastSpace = mb_strrpos($truncated, ' ');

        return mb_substr($truncated, 0, ($lastSpace !== false ? $lastSpace : $maxLength));
    }

    private function brand(CrawledPage $homepage): string
    {
        return trim((string) ($homepage->title ?? ''), " |-\t\n\r");
    }

    private function offerWords(Collection $pages): Collection
    {
        $texts = $pages->map(fn (CrawledPage $page) => collect([
            (string) ($page->title ?? ''),
            (string) ($page->meta['description'] ?? ''),
        ])->implode(' '))->implode(' ');

        $patterns = ['free trial', 'free', 'demo', 'quote', 'book a', 'pricing', 'plans', 'discount', 'guarantee', 'buy now', 'get started'];

        return collect($patterns)
            ->filter(fn (string $pattern) => stripos($texts, $pattern) !== false)
            ->unique()
            ->values();
    }

    private function featureEvidence(CrawledPage $page, string $key): Collection
    {
        return collect(collect($page->features ?? [])->keyBy('feature_key')->get($key)['evidence'] ?? []);
    }
}
