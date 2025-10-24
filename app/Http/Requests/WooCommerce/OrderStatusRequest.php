<?php

namespace App\Http\Requests\WooCommerce;

use App\Services\WooCommerce\OrderStatusService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedStatuses = app(OrderStatusService::class)->allowedStatuses();

        return [
            'status' => [
                'required',
                'string',
                Rule::in($allowedStatuses),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'statusul comenzii',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Selectarea unui status este obligatorie.',
            'status.in' => 'Statusul selectat nu este recunoscut de WooCommerce.',
        ];
    }
}
