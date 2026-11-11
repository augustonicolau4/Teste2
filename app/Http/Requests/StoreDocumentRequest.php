<?php

namespace App\Http\Requests;

use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Enums\LineType;
use App\Domain\Core\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $lineTypes = array_column(LineType::cases(), 'value');
        $methods = array_column(PaymentMethod::cases(), 'value');

        return [
            'document_type'  => ['required', Rule::enum(DocumentType::class)],
            'customer_id'    => ['required', 'integer', 'exists:customers,id'],
            'vehicle_id'     => ['nullable', 'integer', 'exists:vehicles,id'],
            'service_order_id' => ['nullable', 'integer', 'exists:service_orders,id'],
            'issue_date'     => ['nullable', 'date'],
            'due_date'       => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes'          => ['nullable', 'string', 'max:1000'],

            'lines'                     => ['required', 'array', 'min:1'],
            'lines.*.description'       => ['required', 'string', 'max:255'],
            'lines.*.quantity'          => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price'        => ['required', 'numeric', 'min:0'],
            'lines.*.line_type'         => ['nullable', Rule::in($lineTypes)],
            'lines.*.discount_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.retention_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.exemption_code'    => ['nullable', 'string', 'max:10'],
            'lines.*.product_id'        => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.unit_code'         => ['nullable', 'string', 'max:10'],

            'payments'            => ['nullable', 'array'],
            'payments.*.method'   => ['required_with:payments', Rule::in($methods)],
            'payments.*.amount'   => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],

            'deduct_stock' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required'            => 'O documento tem de ter pelo menos uma linha.',
            'lines.*.description.required' => 'Cada linha exige uma descrição.',
            'lines.*.quantity.min'      => 'A quantidade tem de ser superior a zero.',
        ];
    }
}
