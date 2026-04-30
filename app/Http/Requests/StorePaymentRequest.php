<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'merchant_id' => ['required', 'integer', Rule::exists('merchants', 'id')],
            'customer_document' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['PEN'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'merchant_id.required' => 'El identificador del comercio es obligatorio.',
            'merchant_id.exists' => 'No existe un comercio con ese id. Crea comercios con php artisan db:seed o usa un merchant_id válido.',
            'customer_document.required' => 'El documento del cliente es obligatorio.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor que cero.',
            'amount.decimal' => 'El monto admite como máximo 2 decimales.',
            'currency.required' => 'La moneda es obligatoria.',
            'currency.in' => 'Por ahora solo se acepta la moneda PEN.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'merchant_id' => 'comercio',
            'customer_document' => 'documento del cliente',
            'amount' => 'monto',
            'currency' => 'moneda',
            'description' => 'descripción',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge([
                'currency' => strtoupper($this->input('currency')),
            ]);
        }
    }
}
