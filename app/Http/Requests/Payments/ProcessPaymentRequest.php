<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'details' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'The order ID is required to process payment.',
            'order_id.exists' => 'The specified order does not exist.',
            'payment_method.required' => 'A payment method is required (e.g. credit_card, paypal, stripe).',
            'amount.min' => 'The payment amount must be greater than zero.',
        ];
    }
}
