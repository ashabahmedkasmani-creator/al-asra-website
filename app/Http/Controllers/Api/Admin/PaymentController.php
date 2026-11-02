<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'method' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Payment::with([
            'order:id,reference,customer_name,customer_phone,grand_total,status',
            'verifier:id,name',
        ])->latest('id');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['method'])) {
            $query->where('method', $validated['method']);
        }
        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->whereHas('order', fn ($order) => $order
                ->where('reference', strtoupper($search))
                ->orWhere('customer_phone', $search)
                ->orWhere('customer_name', 'like', '%'.$search.'%'));
        }

        return response()->json([
            'data' => $query->paginate($validated['per_page'] ?? 25)->withQueryString(),
        ]);
    }
}
