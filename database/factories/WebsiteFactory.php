<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Services\Security\UrlValidator;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebsiteFactory extends Factory
{
    public function definition(): array
    {
        $url = 'https://'.fake()->unique()->domainName();

        $result = app(UrlValidator::class)->validate($url);

        assert($result['valid'] === true);

        return [
            'project_id' => $project = Project::factory()->create(),
            'user_id' => $project->user_id,
            'url' => $url,
            'normalized_url' => $result['normalized_url'],
            'url_hash' => $result['url_hash'],
            'host' => $result['host'],
            'label' => null,
            'last_analyzed_at' => null,
        ];
    }
}
