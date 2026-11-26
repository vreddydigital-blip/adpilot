<?php

namespace App\Services\Advisor;

interface AiProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    public function recommend(array $context): array;
}
