<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v;
        $this->merge([
            'customer_name' => $clean($this->customer_name),
            'customer_phone' => Phone::normalize($this->customer_phone),
            'customer_email' => is_string($this->customer_email) ? strtolower(trim($this->customer_email)) : $this->customer_email,
            'address_line' => $clean($this->address_line),
            'city' => $clean($this->city),
            'province' => $clean($this->province),
            'postal_code' => $clean($this->postal_code),
            'notes' => $clean($this->notes),
            'coupon_code' => is_string($this->coupon_code) ? strtoupper(trim($this->coupon_code)) : $this->coupon_code,
        ]);
    }

    public function rules(): array
    {
        $hasAddressId = $this->filled('address_id');
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'regex:/^03\d{9}$/'],
            'customer_email' => ['nullable', 'email:rfc', 'max:150'],
            'address_id' => ['nullable', 'integer'],
            'address_line' => [$hasAddressId ? 'nullable' : 'required', 'string', 'min:5', 'max:255'],
            'city' => [$hasAddressId ? 'nullable' : 'required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:cod,bank_transfer'],
            'coupon_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/'],
            'items' => ['nullable', 'array', 'min:1', 'max:'.config('alasra.max_cart_lines')],
            'items.*.product_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:'.config('alasra.max_line_quantity')],
            // NOTE: any client-sent price/total/shipping field is simply ignored.
        ];
    }

    public function messages(): array
    {
        return ['customer_phone.regex' => 'Enter a valid Pakistani mobile number, e.g. 03XX XXXXXXX.'];
    }
}
