<?php

namespace App\Services\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use Illuminate\Support\Collection;

class LeadReadinessService
{
    public const VERSION = 'v1.0';

    public const MODULE_KEY = 'lead_intelligence';

    public function analyze(AnalysisJob $job): array
    {
        $pages = $job->crawledPages()->orderBy('depth')->orderBy('id')->get();

        if ($pages->isEmpty()) {
            return [
                'module_key' => self::MODULE_KEY,
                'score' => null,
                'version' => self::VERSION,
                'label' => 'Heuristic Lead Capture Readiness Score',
                'methodology' => 'presence-based heuristic from crawl evidence; not real lead quality',
                'summary' => 'No crawl evidence available, so no lead-readiness score is calculated.',
                'forms' => collect(),
                'contact_methods' => [],
                'insights' => [],
                'confidence' => 'low',
            ];
        }

        $forms = $this->collectForms($pages);
        $contacts = $this->collectContacts($pages);
        $insights = $this->insights($pages, $forms, $contacts);
        $score = $this->score($pages, $forms, $contacts);

        return [
            'module_key' => self::MODULE_KEY,
            'score' => $score,
            'version' => self::VERSION,
            'label' => 'Heuristic Lead Capture Readiness Score',
            'methodology' => 'presence-based heuristic from crawl evidence; not real lead quality',
            'summary' => 'Presence-based evaluation of forms, contact channels and booking links across '.$pages->count().' crawled page(s). Actual lead volume and quality require CRM integrations (future phase).',
            'forms' => $forms,
            'contact_methods' => $contacts,
            'insights' => $insights,
            'confidence' => $score === null ? 'low' : 'medium',
        ];
    }

    public function insights(Collection $pages, Collection $forms, array $contacts): array
    {
        $insights = [];

        $missing = array_filter([
            $contacts['email'] ? null : 'no email link (mailto:)',
            $contacts['phone'] ? null : 'no phone link (tel:)',
            $contacts['whatsapp'] ? null : 'no WhatsApp click-to-chat',
            $contacts['booking'] ? null : 'no booking / calendar link',
        ]);

        if ($forms->isEmpty()) {
            $insights[] = [
                'insight_key' => 'no_forms',
                'priority' => 'high',
                'title' => 'No lead capture form detected',
                'evidence' => ['No form with a submit control was found on any crawled page (password forms excluded).'],
                'recommendation' => 'Add a short lead form (name, email, message) to the pages ads will point to.',
                'effort' => 'low',
            ];
        } else {
            $measured = $forms->filter(fn (array $form) => $form['field_count'] !== null);
            $heavy = $measured->filter(fn (array $form) => $form['field_count'] > 4);

            if ($heavy->isNotEmpty()) {
                $insights[] = [
                    'insight_key' => 'form_friction',
                    'priority' => 'medium',
                    'title' => 'Forms with high field friction',
                    'evidence' => [$heavy->count().' form(s) with more than 4 fields.', 'Largest detected form: '.($heavy->max('field_count')).' fields.'],
                    'recommendation' => 'Reduce form fields to the minimum you actually use; every extra field lowers completion.',
                    'effort' => 'low',
                ];
            }
        }

        if ($missing !== []) {
            $insights[] = [
                'insight_key' => 'missing_contact_channels',
                'priority' => count($missing) >= 3 ? 'high' : 'medium',
                'title' => 'Missing contact channels',
                'evidence' => $missing,
                'recommendation' => 'Add at least two direct contact channels near CTAs on every key page.',
                'effort' => 'low',
            ];
        }

        $homepage = $pages->firstWhere('depth', 0) ?? $pages->first();

        if ($homepage && ! $this->featurePresent($homepage, 'cta_buttons') && ! $this->featurePresent($homepage, 'signup_forms')) {
            $insights[] = [
                'insight_key' => 'homepage_cta_consistency',
                'priority' => 'medium',
                'title' => 'No visible next step on the entry page',
                'evidence' => ['No CTA link or signup form detected on the entry page.'],
                'recommendation' => 'End the entry viewport with a consistent call to action that matches your ads.',
                'effort' => 'low',
            ];
        }

        $contactPage = $pages->first(fn (CrawledPage $page) => (bool) preg_match('/contact|kontakt|about/i', ($page->url.' '.$page->title)));

        if ($contactPage === null) {
            $insights[] = [
                'insight_key' => 'contact_page',
                'priority' => 'medium',
                'title' => 'No contact page detected',
                'evidence' => ['No crawled URL or title contains contact/about patterns within the 20-page cap.'],
                'recommendation' => 'Create a linked contact page; many ad platforms check its existence during review.',
                'effort' => 'low',
            ];
        }

        return $insights;
    }

    private function score(Collection $pages, Collection $forms, array $contacts): ?int
    {
        $points = 0;

        if ($forms->isNotEmpty()) {
            $points += 40;

            if ($forms->contains(fn (array $form) => $form['field_count'] !== null && $form['field_count'] <= 3)) {
                $points += 10;
            }
        }

        $points += ($contacts['email'] ? 10 : 0)
            + ($contacts['phone'] ? 10 : 0)
            + ($contacts['whatsapp'] ? 5 : 0)
            + ($contacts['booking'] ? 15 : 0);

        $homepage = $pages->firstWhere('depth', 0) ?? $pages->first();

        if ($homepage && $this->featurePresent($homepage, 'cta_buttons')) {
            $points += 10;
        }

        return min(95, $points);
    }

    private function collectForms(Collection $pages): Collection
    {
        return collect($pages->flatMap(fn (CrawledPage $page) => $this->featureEvidence($page, 'lead_forms')))
            ->map(function ($item) {
                $raw = is_array($item) ? (string) ($item['label'] ?? json_encode($item)) : (string) $item;

                if (preg_match('/^(\d+)-field (get|post) form$/i', trim($raw), $matches)) {
                    return ['field_count' => (int) $matches[1], 'method' => strtolower($matches[2]), 'sample' => strtolower($raw)];
                }

                return ['field_count' => null, 'method' => null, 'sample' => trim($raw)];
            })
            ->filter(fn (array $form) => $form['field_count'] !== null || $form['sample'] !== '')
            ->values();
    }

    private function collectContacts(Collection $pages): array
    {
        return [
            'email' => $pages->contains(fn (CrawledPage $page) => $this->featurePresent($page, 'email_links')),
            'phone' => $pages->contains(fn (CrawledPage $page) => $this->featurePresent($page, 'phone_links')),
            'whatsapp' => $pages->contains(fn (CrawledPage $page) => $this->featurePresent($page, 'whatsapp_links')),
            'booking' => $pages->contains(fn (CrawledPage $page) => $this->featurePresent($page, 'booking_links')),
        ];
    }

    private function featurePresent(CrawledPage $page, string $key): bool
    {
        return (bool) (collect($page->features ?? [])->keyBy('feature_key')->get($key)['present'] ?? false);
    }

    private function featureEvidence(CrawledPage $page, string $key): Collection
    {
        return collect(collect($page->features ?? [])->keyBy('feature_key')->get($key)['evidence'] ?? []);
    }
}
