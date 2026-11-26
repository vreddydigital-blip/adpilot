<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:pdf,csv'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.in' => 'Format must be either pdf or csv.',
        ];
    }
}
