<?php

namespace App\Http\Requests;

use App\Services\Analysis\BudgetCalculationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', Rule::in(array_keys(BudgetCalculationService::CHANNELS))],
            'budget' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'cpc' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'lead_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'conversion_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'average_order_value' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'gross_margin' => ['required', 'numeric', 'gt:0', 'max:100'],
            'target_cpa' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'gross_margin.gt' => 'Gross margin must be greater than zero so break-even ROAS is defined.',
        ];
    }
}
