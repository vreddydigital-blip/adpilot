<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->isMethod('POST')) {
            $rules['name'][] = 'unique:projects,name,NULL,id,user_id,'.($this->user()?->id ?? 0);
        }

        return $rules;
    }
}
