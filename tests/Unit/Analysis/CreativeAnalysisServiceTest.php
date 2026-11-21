<?php

namespace Tests\Unit\Analysis;

use App\Models\AnalysisJob;
use App\Models\CrawledPage;
use App\Models\Website;
use App\Services\Analysis\CreativeAnalysisService;
use Database\Factories\AnalysisJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreativeAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'Affordable Bookkeeping Services For Small Businesses';

    private const H1 = 'Bookkeeping that saves you hours every month';

    private const DESCRIPTION = 'Outsourced bookkeeping for small businesses. Monthly plans, no contracts, and a free consultation with every quote request.';

    private AnalysisJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $website = Website::factory()->forUrl('https://example.com')->create();
        $this->job = AnalysisJobFactory::new()->forWebsite($website)->create();
    }

    private function page(array $overrides = []): CrawledPage
    {
        return CrawledPage::create([...array_merge([
            'url' => 'https://example.com/',
            'final_url' => 'https://example.com/',
            'http_status' => 200,
            'title' => self::TITLE,
            'meta' => ['description' => self::DESCRIPTION],
            'headings' => ['h1' => [self::H1]],
            'text_length' => 900,
            'load_time_ms' => 500,
            'content_hash' => hash('sha256', uniqid('', true)),
            'features' => [
                ['feature_key' => 'cta_buttons', 'present' => true, 'count' => 2, 'evidence' => ['Get a free quote', 'Book a demo']],
            ],
            'depth' => 0,
        ], $overrides), 'analysis_job_id' => $this->job->id]);
    }

    public function test_generates_ads_within_platform_limits(): void
    {
        $this->page();

        $result = app(CreativeAnalysisService::class)->generate($this->job->refresh());

        foreach ($result['google_headlines'] as $headline) {
            $this->assertGreaterThanOrEqual(5, $headline['char_length']);
            $this->assertLessThanOrEqual(30, $headline['char_length']);
        }

        foreach ($result['google_descriptions'] as $description) {
            $this->assertGreaterThanOrEqual(30, $description['char_length']);
            $this->assertLessThanOrEqual(90, $description['char_length']);
        }

        foreach ($result['meta_headlines'] as $headline) {
            $this->assertLessThanOrEqual(40, $headline['char_length']);
        }

        foreach ($result['meta_primary_text'] as $text) {
            $this->assertLessThanOrEqual(125, $text['char_length']);
        }
    }

    public function test_headlines_are_derived_from_page_content_and_ctas(): void
    {
        $this->page();

        $result = app(CreativeAnalysisService::class)->generate($this->job->refresh());

        $texts = array_column($result['google_headlines'], 'text');

        $this->assertContains('Get a free quote', $texts);
        $this->assertContains('Affordable Bookkeeping', $texts);
        $this->assertContains('Bookkeeping that saves you', $texts);

        $descriptions = array_column($result['google_descriptions'], 'text');

        $this->assertNotEmpty($descriptions);
        $this->assertStringStartsWith('Outsourced bookkeeping for small businesses', $descriptions[0]);
    }

    public function test_long_content_is_truncated_to_limits(): void
    {
        $this->page([
            'meta' => ['description' => str_repeat('bookkeeping ', 30)],
        ]);

        $result = app(CreativeAnalysisService::class)->generate($this->job->refresh());

        foreach ($result['google_descriptions'] as $description) {
            $this->assertLessThanOrEqual(90, $description['char_length']);
        }
    }

    public function test_message_match_is_computed_without_ctr_predictions(): void
    {
        $this->page();

        $result = app(CreativeAnalysisService::class)->generate($this->job->refresh());

        $this->assertIsInt($result['message_match']['overlap_percent']);
        $this->assertGreaterThanOrEqual(0, $result['message_match']['overlap_percent']);
        $this->assertLessThanOrEqual(100, $result['message_match']['overlap_percent']);
        $this->assertStringContainsString('does not predict CTR', $result['message_match']['note']);
    }

    public function test_source_and_disclaimer_are_reported(): void
    {
        $this->page();

        $result = app(CreativeAnalysisService::class)->generate($this->job->refresh());

        $this->assertSame('https://example.com/', $result['source']['entry_page']);
        $this->assertSame(self::H1, $result['source']['h1']);
        $this->assertStringContainsString('No CTR or conversion predictions are made', $result['disclaimer']);
    }

    public function test_no_pages_returns_empty_array(): void
    {
        $result = app(CreativeAnalysisService::class)->generate($this->job->refresh());

        $this->assertSame([], $result);
    }
}
