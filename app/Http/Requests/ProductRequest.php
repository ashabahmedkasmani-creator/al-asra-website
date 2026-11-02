<?php

namespace App\Http\Requests;

use App\Enums\Availability;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool { return true; } // route middleware enforces products.manage

    protected function prepareForValidation(): void
    {
        // Plain text only: tags are stripped so stored content can never carry markup.
        foreach (['name', 'short_description', 'description', 'sku'] as $f) {
            if (is_string($this->input($f))) {
                $this->merge([$f => trim(strip_tags($this->input($f)))]);
            }
        }
    }

    public function rules(): array
    {
        $req = $this->isMethod('POST') ? 'required' : 'sometimes';
        $product = $this->route('product');
        $id = $product instanceof Product ? $product->id : null;

        return [
            'name' => [$req, 'string', 'min:2', 'max:200'],
            'sku' => [$req, 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->ignore($id)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'specifications' => ['nullable', 'array', 'max:30'],
            'specifications.*' => ['nullable', 'string', 'max:500'],
            'price' => [$req, 'numeric', 'min:0.01', 'max:10000000', 'decimal:0,2'],
            'sale_price' => ['nullable', 'numeric', 'min:0.01', 'max:10000000', 'decimal:0,2'],
            'availability' => ['sometimes', Rule::enum(Availability::class)],
            'track_inventory' => ['sometimes', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_featured' => ['sometimes', 'boolean'],
            'show_on_home' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $product = $this->route('product');
            $price = $this->input('price', $product instanceof Product ? $product->price : null);
            $sale = $this->input('sale_price');
            if ($sale !== null && $price !== null && (float) $sale >= (float) $price) {
                $v->errors()->add('sale_price', 'The sale price must be lower than the regular price.');
            }
            $tracking = $this->has('track_inventory') ? $this->boolean('track_inventory') : ($product?->track_inventory ?? false);
            $stock = $this->has('stock_quantity') ? $this->input('stock_quantity') : ($product?->stock_quantity);
            if ($tracking && $stock === null) {
                $v->errors()->add('stock_quantity', 'Stock quantity is required when inventory tracking is on.');
            }
        }];
    }
}
