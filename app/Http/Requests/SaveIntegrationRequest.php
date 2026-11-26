<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $provider = $this->route('provider');

        return [
            'credentials' => ['required', 'array', 'max:10'],
            'credentials.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'credentials.required' => 'Credentials are required to store an integration.',
            'credentials.*.max' => 'Each credential value must not exceed 500 characters.',
        ];
    }

    public function preparedCredentials(): array
    {
        $provider = $this->route('provider');
        $allowed = config("integrations.providers.$provider.credentials", []);
        $input = (array) $this->validated('credentials');

        return collect($allowed)
            ->mapWithKeys(fn (string $field) => [$field => isset($input[$field]) ? trim((string) $input[$field]) : null])
            ->all();
    }
}
