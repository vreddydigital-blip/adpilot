<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'website_id' => $this->website_id,
            'status' => $this->status,
            'attempt' => $this->attempt,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'crawled_pages' => $this->crawled_pages,
            'crawl_duration_ms' => $this->crawl_duration_ms,
            'failure_reason' => $this->failure_reason,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
