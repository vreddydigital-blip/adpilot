<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projectId = $this->input('project_id');

        if (! is_numeric($projectId)) {
            return true;
        }

        return Project::whereKey($projectId)->where('user_id', $this->user()?->id)->exists();
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('user_id', $this->user()?->id)],
            'url' => ['required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.exists' => 'The selected project does not exist or does not belong to you.',
        ];
    }
}
