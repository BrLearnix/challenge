<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankNotificationRequest extends FormRequest
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
            'event_id' => ['required', 'string', 'max:128'],
            'bank_transaction_id' => ['required', 'string', 'max:128'],
            'payment_code' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['PEN'])],
            'status' => ['required', 'string', 'max:32'],
            'paid_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_code.required' => 'El código de pago es obligatorio.',
            'event_id.required' => 'event_id es obligatorio.',
            'bank_transaction_id.required' => 'bank_transaction_id es obligatorio.',
            'amount.decimal' => 'El monto admite como máximo 2 decimales.',
            'currency.in' => 'Por ahora solo se acepta PEN.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge([
                'currency' => strtoupper($this->input('currency')),
            ]);
        }

        if ($this->has('status') && is_string($this->input('status'))) {
            $this->merge([
                'status' => strtoupper($this->input('status')),
            ]);
        }
    }
}
