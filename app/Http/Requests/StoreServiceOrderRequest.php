<?php

namespace App\Http\Requests;

use App\Domain\Core\Enums\LineType;
use App\Domain\Core\Enums\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id'  => ['required', 'integer', 'exists:customers,id'],
            'vehicle_id'   => ['required', 'integer', 'exists:vehicles,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'mileage_in'   => ['nullable', 'integer', 'min:0'],
            'fuel_level'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_complaint' => ['nullable', 'string', 'max:2000'],
            'promised_at'  => ['nullable', 'date'],

            'items'              => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.line_type'  => ['required', Rule::enum(LineType::class)],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_type'   => ['nullable', Rule::enum(TaxType::class)],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}
