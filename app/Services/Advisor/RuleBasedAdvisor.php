<?php

namespace App\Services\Advisor;

class RuleBasedAdvisor implements AiProvider
{
    public function name(): string
    {
        return 'rule_based_v1';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function recommend(array $context): array
    {
        $items = $context['items'] ?? [];
        $grouped = $this->group($items);
        $recommendations = $this->consolidate($grouped, $items);

        return [
            'summary' => $this->summary($context, $items, $recommendations),
            'recommendations' => $recommendations,
        ];
    }

    private function group(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $topic = $this->topic($item['key'], $item['title']);
            $grouped[$topic]['items'][] = $item;
        }

        foreach ($grouped as $topic => $group) {
            $grouped[$topic]['priority'] = $this->rankings(array_column($group['items'], 'priority'))[0];
        }

        uasort($grouped, function (array $a, array $b): int {
            $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
            $diff = ($priorityOrder[$a['priority']] ?? 3) <=> ($priorityOrder[$b['priority']] ?? 3);

            if ($diff !== 0) {
                return $diff;
            }

            return count($b['items']) <=> count($a['items']);
        });

        return $grouped;
    }

    private function consolidate(array $grouped, array $items): array
    {
        $recommendations = [];
        $position = 0;

        foreach ($grouped as $topic => $group) {
            if (count($recommendations) >= 10) {
                break;
            }

            $top = $group['items'][0];
            $modules = array_values(array_unique(array_column($group['items'], 'module')));
            $evidence = [];

            foreach ($group['items'] as $item) {
                foreach ((array) ($item['evidence'] ?? []) as $line) {
                    $line = mb_substr(trim((string) $line), 0, 200);
                    if ($line !== '' && ! in_array($line, $evidence, true)) {
                        $evidence[] = $line;
                    }
                    if (count($evidence) >= 4) {
                        break 2;
                    }
                }
            }

            $position++;

            $action = filled($top['action'] ?? null)
                ? $top['action']
                : 'Resolve: '.$top['title'];

            $recommendations[] = [
                'recommendation_key' => $topic,
                'priority' => $group['priority'],
                'title' => $top['title'],
                'rationale' => count($group['items']).' cross-module finding(s) consolidated from: '.implode(', ', $modules).'.',
                'evidence' => $evidence === [] ? null : $evidence,
                'action' => $action,
                'effort' => $top['effort'] ?? null,
                'expected_impact' => $top['impact'] ?? null,
                'confidence' => $top['confidence'] ?? null,
                'module_source' => $modules,
                'position' => $position,
                'source' => $this->name(),
            ];
        }

        return $recommendations;
    }

    private function topic(string $key, string $title): string
    {
        $haystack = mb_strtolower($key.' '.$title);

        $rules = [
            'lead_capture' => ['form', 'lead'],
            'contact_paths' => ['contact', 'email', 'phone', 'whatsapp', 'booking'],
            'cta_effectiveness' => ['cta'],
            'mobile_usability' => ['mobile', 'viewport'],
            'metadata_quality' => ['title', 'meta', 'description', 'seo'],
            'technical_health' => ['https', 'status_code', 'crawl_error', 'certificate', 'ssl'],
            'load_performance' => ['load', 'speed'],
            'content_depth' => ['content'],
            'trust_signals' => ['trust', 'testimonial', 'signal'],
            'offer_clarity' => ['offer', 'value_prop', 'value_proportion'],
            'messaging' => ['message', 'brand', 'headline', 'creative'],
        ];

        foreach ($rules as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $topic;
                }
            }
        }

        $clean = preg_replace('/[^a-z0-9_]/', '', mb_strtolower($key));

        return $clean === null || $clean === '' ? 'other_general' : 'other_'.$clean;
    }

    private function rankings(array $priorities): array
    {
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];

        $unique = array_values(array_unique($priorities));

        usort($unique, fn (string $a, string $b): int => ($order[$a] ?? 3) <=> ($order[$b] ?? 3));

        return $unique;
    }

    private function summary(array $context, array $items, array $recommendations): string
    {
        $scores = $context['scores'] ?? [];
        $pageCount = (int) ($context['page_count'] ?? 0);

        if (count(array_filter($scores, fn ($value) => $value !== null)) === 0) {
            return 'No completed module results exist for this analysis yet, so no recommendations can be generated. Re-run the analysis to populate the intelligence modules first.';
        }

        $parts = [];
        $labels = [
            'campaign_readiness' => 'Campaign Readiness',
            'technical_audit' => 'Technical Audit',
            'conversion_opportunity' => 'Conversion Opportunity',
            'lead_intelligence' => 'Lead Readiness',
        ];

        foreach ($labels as $key => $label) {
            if ($scores[$key] !== null) {
                $parts[] = $label.' '.$scores[$key].'/100';
            }
        }

        $topics = count($this->group($items));

        return 'Cross-module review of the '
            .$pageCount.'-page crawl: '
            .'('.implode(', ', $parts).'; heuristic evidence-based scores, not validated ML predictions). '
            .count($items).' module finding(s) were de-duplicated into '
            .$topics.' topic(s) and ranked into '.count($recommendations).' prioritised recommendation(s). '
            .'No advertising-platform or revenue data is used; this advisor never constitutes a performance forecast.';
    }
}
