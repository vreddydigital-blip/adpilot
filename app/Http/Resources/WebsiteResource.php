<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'url' => $this->url,
            'normalized_url' => $this->normalized_url,
            'host' => $this->host,
            'label' => $this->label,
            'last_analyzed_at' => $this->last_analyzed_at?->toIso8601String(),
            'latest_analysis_job' => new AnalysisJobResource($this->whenLoaded('latestAnalysisJob')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
