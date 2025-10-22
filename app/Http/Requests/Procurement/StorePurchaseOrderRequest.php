<?php

namespace App\Http\Requests\Procurement;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'exists:procurement_suppliers,id'],
            'supplier.name' => ['required_without:supplier_id', 'string', 'max:255'],
            'supplier.contact_name' => ['nullable', 'string', 'max:255'],
            'supplier.email' => ['nullable', 'email', 'max:255'],
            'supplier.phone' => ['nullable', 'string', 'max:50'],
            'supplier.reference' => ['nullable', 'string', 'max:100'],
            'supplier.notes' => ['nullable', 'string'],
            'po_number' => ['required', 'string', 'max:100', 'unique:procurement_purchase_orders,po_number'],
            'status' => ['required', Rule::in(PurchaseOrder::STATUSES)],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produs_id' => ['nullable', 'exists:produse,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
