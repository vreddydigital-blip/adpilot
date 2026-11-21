<?php

namespace Database\Factories;

use App\Models\AnalysisJob;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnalysisJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'status' => AnalysisJob::STATUS_PENDING,
        ];
    }

    public function forWebsite(Website $website): static
    {
        return $this->state(fn () => [
            'website_id' => $website->id,
            'user_id' => $website->user_id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisJob::STATUS_COMPLETED,
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisJob::STATUS_FAILED,
            'failure_reason' => 'crawl_error',
            'error_message' => 'Simulated failure for tests',
            'finished_at' => now(),
        ]);
    }
}
