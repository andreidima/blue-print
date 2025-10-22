<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'create_movements' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
