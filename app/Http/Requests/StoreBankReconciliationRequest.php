<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankReconciliationRequest extends FormRequest
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
            'bank' => ['required', 'string', 'max:64'],
            'process_date' => ['required', 'date_format:Y-m-d'],
            'movements' => ['required', 'array', 'min:1'],
            'movements.*.bank_movement_id' => ['required', 'string', 'max:128'],
            'movements.*.bank_transaction_id' => ['nullable', 'string', 'max:128'],
            'movements.*.payment_code' => ['nullable', 'string', 'max:64'],
            'movements.*.amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'movements.*.currency' => ['required', 'string', 'size:3', Rule::in(['PEN'])],
            'movements.*.paid_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movements.required' => 'Debe incluir al menos un movimiento.',
            'movements.*.amount.decimal' => 'Cada monto admite como máximo 2 decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $movements = $this->input('movements');
        if (! is_array($movements)) {
            return;
        }

        foreach ($movements as $i => $mov) {
            if (! is_array($mov)) {
                continue;
            }
            if (isset($mov['currency']) && is_string($mov['currency'])) {
                $movements[$i]['currency'] = strtoupper($mov['currency']);
            }
        }

        $this->merge(['movements' => $movements]);
    }
}
