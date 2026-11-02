<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->subDays(29)->toDateString();
        $to = $validated['to'] ?? today()->toDateString();
        $period = [$from.' 00:00:00', $to.' 23:59:59'];
        $orders = Order::whereBetween('created_at', $period);

        return response()->json(['data' => [
            'period' => ['from' => $from, 'to' => $to],
            'orders_total' => (clone $orders)->count(),
            'orders_by_status' => (clone $orders)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'gross_revenue' => (float) (clone $orders)
                ->where('payment_status', PaymentStatus::Paid->value)
                ->sum('grand_total'),
            'pending_revenue' => (float) (clone $orders)
                ->where('payment_status', PaymentStatus::Pending->value)
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])
                ->sum('grand_total'),
            'customers_total' => User::where('role', Role::Customer->value)->count(),
            'products_total' => Product::count(),
            'daily' => (clone $orders)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(grand_total) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'top_products' => OrderItem::query()
                ->selectRaw('product_name, SUM(quantity) as units, SUM(line_total) as revenue')
                ->whereHas('order', fn ($order) => $order->whereBetween('created_at', $period))
                ->groupBy('product_name')
                ->orderByDesc('units')
                ->limit(10)
                ->get(),
        ]]);
    }
}
