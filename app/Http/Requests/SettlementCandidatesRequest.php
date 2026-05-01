<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettlementCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
            'merchant_id' => ['sometimes', 'integer', Rule::exists('merchants', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'as_of.date_format' => 'La fecha as_of debe tener formato YYYY-MM-DD.',
            'merchant_id.exists' => 'No existe un comercio con ese id.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'as_of' => 'fecha de corte',
            'merchant_id' => 'comercio',
        ];
    }
}
